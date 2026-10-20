<script setup lang="ts">
import { computed, ref, watch, nextTick } from "vue";
import { useForm, router } from "@inertiajs/vue3";
import axios from "axios";
import FinancialField from "@/Components/UI/FinancialField.vue";
import DecimalHint from "@/Components/UI/DecimalHint.vue";
import { expenseMultipart } from "@/Utils/creationDraft";
import { requestErrors, formFieldErrors } from "@/Utils/validation";
import type { ExpenseEditorDraft } from "@/Types/creation";
import { formatMoney, formatPesos, formatDateOnly, formatRate } from "@/Utils/format";
import { PlusIcon, PencilIcon, TrashIcon } from "@heroicons/vue/24/outline";
import { useRoute } from "@/Composables/useRoute";
import { inputFile } from "@/Utils/input";
import type {
    ImportDetail,
    NamedRecord,
    ExpenseData,
    ExpenseMutation,
} from "@/Types/financial";
const props = defineProps<{
    trip: ImportDetail;
    categories: NamedRecord[];
    embedded?: boolean;
    draftState?: ExpenseEditorDraft | null;
}>();
const emit = defineEmits<{
    delete: [expense: ExpenseData];
    draft: [state: ExpenseEditorDraft | null];
}>();
const route = useRoute();
const open = ref(false);
const editingId = ref<number | null>(null);
const editorForm = ref<HTMLFormElement | null>(null);
const errorSummary = ref<HTMLElement | null>(null);
const addButton = ref<HTMLButtonElement | null>(null);
let editorTrigger: HTMLElement | null = null;
const rememberTrigger = () => {
    editorTrigger =
        document.activeElement instanceof HTMLElement
            ? document.activeElement
            : null;
};
const focusEditorError = async () => {
    await nextTick();
    const field = editorForm.value?.querySelector<HTMLElement>(
        '[aria-invalid="true"]',
    );
    (field || errorSummary.value)?.focus();
};
const errorAttrs = (
    id: string,
    field: keyof ExpenseMutation,
    help?: string,
) => ({
    "aria-invalid": !!form.errors[field],
    "aria-describedby":
        [help, form.errors[field] && `${id}-error`].filter(Boolean).join(" ") ||
        undefined,
});
const editingExpense = computed(() =>
    props.trip.expenses.find((expense) => expense.id === editingId.value),
);
const documentsById = computed(
    () =>
        new Map(
            props.trip.documents.map((document) => [document.id, document]),
        ),
);
const receiptLink = (expense: ExpenseData) =>
    expense.document_id
        ? documentsById.value.get(expense.document_id)?.download_url
        : undefined;
const currentReceipt = computed(() =>
    editingExpense.value?.document_id
        ? documentsById.value.get(editingExpense.value.document_id)
        : undefined,
);
const editable = computed(() => props.trip.estado === "activo");
const rules = {
    value: "Valor de compra",
    units: "Unidades",
    weight: "Peso",
    volume: "Volumen",
    direct: "Producto específico",
};
const form = useForm<ExpenseMutation>({
    expense_category_id: "",
    concepto: "",
    moneda: props.trip.moneda_trabajo,
    monto: "",
    rate_mode: "estimated",
    applied_rate: "",
    rate_source: "",
    rate_date: "",
    allocation_method: "value",
    target_product_id: "",
    comprobante: null,
    eliminar_comprobante: false,
    expected_revision: props.trip.revision,
});
const add = () => {
    if (open.value || embedSaving.value || form.processing) return;
    rememberTrigger();
    missingReceipt.value = "";
    form.reset();
    form.clearErrors();
    form.expected_revision = props.trip.revision;
    editingId.value = null;
    open.value = true;
};
const edit = (expense: ExpenseData) => {
    if (open.value || embedSaving.value || form.processing) return;
    rememberTrigger();
    missingReceipt.value = "";
    form.clearErrors();
    Object.assign(form, {
        expense_category_id: expense.expense_category_id || "",
        concepto: expense.concepto,
        moneda: expense.moneda,
        monto: expense.monto,
        rate_mode: expense.rate_mode,
        applied_rate: expense.applied_rate || "",
        rate_source:
            expense.rate_source === "legacy-unverified"
                ? ""
                : expense.rate_source || "",
        rate_date: expense.rate_date || "",
        allocation_method: expense.allocation_method,
        target_product_id: expense.target_product_id || "",
        comprobante: null,
        eliminar_comprobante: false,
        expected_revision: props.trip.revision,
    });
    editingId.value = expense.id;
    open.value = true;
};
const close = () => {
    if (!form.processing && !embedSaving.value) {
        open.value = false;
        editingId.value = null;
    }
};
const embedSaving = ref(false);
watch(
    open,
    async (value, previous) => {
        await nextTick();
        if (value)
            editorForm.value
                ?.querySelector<HTMLInputElement>("#expense-concept")
                ?.focus();
        else if (previous)
            (editorTrigger?.isConnected
                ? editorTrigger
                : addButton.value
            )?.focus();
    },
    { immediate: true },
);
const submit = async () => {
    if (!editable.value) {
        form.setError(
            "expected_revision",
            "Esta importación no admite cambios. Tu borrador se conserva.",
        );
        await focusEditorError();
        return;
    }
    if (props.embedded) {
        if (embedSaving.value) return;
        embedSaving.value = true;
        form.clearErrors();
        const { comprobante, ...fields } = form.data();
        try {
            await axios.post(
                editingId.value
                    ? route("trips.expenses.update", [
                          props.trip.id,
                          editingId.value,
                      ])
                    : route("trips.expenses.store", props.trip.id),
                expenseMultipart(fields, comprobante, !!editingId.value),
                { headers: { Accept: "application/json" } },
            );
            open.value = false;
            form.reset();
            router.reload();
        } catch (failure) {
            Object.assign(
                form.errors,
                formFieldErrors(
                    requestErrors(
                        failure,
                        "save",
                        "No se pudo guardar el gasto. Tu borrador se conserva.",
                    ),
                ),
            );
        } finally {
            embedSaving.value = false;
            if (Object.keys(form.errors).length) await focusEditorError();
        }
        return;
    }
    const options = {
        forceFormData: true,
        preserveScroll: true,
        onFinish: () => void focusEditorError(),
        onSuccess: () => {
            open.value = false;
            form.reset();
        },
    };
    form.transform((data) => ({
        ...data,
        eliminar_comprobante: data.comprobante
            ? false
            : data.eliminar_comprobante,
        ...(editingId.value ? { _method: "put" } : {}),
    })).post(
        editingId.value
            ? route("trips.expenses.update", [props.trip.id, editingId.value])
            : route("trips.expenses.store", props.trip.id),
        options,
    );
};
const missingReceipt = ref(props.draftState?.receipt_name || "");
if (props.draftState) {
    Object.assign(form, props.draftState.fields);
    editingId.value = props.draftState.id;
    open.value = true;
}
watch(
    () => ({ open: open.value, id: editingId.value, data: form.data() }),
    () => {
        if (!props.embedded) return;
        const { comprobante, ...fields } = form.data();
        emit(
            "draft",
            open.value
                ? {
                      id: editingId.value,
                      fields: { ...fields },
                      receipt_name: comprobante?.name || missingReceipt.value,
                  }
                : null,
        );
    },
    { deep: true },
);
</script>
<template>
    <section class="f-panel">
        <div class="f-panel-heading">
            <div>
                <h2>Gastos de importación</h2>
                <p class="f-hint">
                    {{ trip.expenses.length }}
                    {{ trip.expenses.length === 1 ? "gasto" : "gastos" }} ·
                    conversión y reparto explícitos
                </p>
            </div>
            <button
                ref="addButton"
                v-if="editable"
                class="f-button f-button-primary"
                @click="add"
                :disabled="open || embedSaving || form.processing"
            >
                <PlusIcon /> Agregar gasto
            </button>
        </div>
        <form
            v-if="open"
            ref="editorForm"
            class="f-panel-body"
            novalidate
            @submit.prevent="submit"
        >
            <p v-if="embedded" class="f-hint" style="margin-bottom: 12px">
                Los campos del editor se conservan con el borrador. El
                comprobante se adjunta al guardar el gasto.
            </p>
            <p v-if="missingReceipt && !form.comprobante" class="f-notice">
                Vuelve a seleccionar «{{ missingReceipt }}»: ese archivo todavía
                no se había adjuntado al gasto.
            </p>
            <p v-if="!editable" class="f-notice" role="status">
                La importación ya no admite cambios. Tu borrador sigue visible;
                puedes cancelarlo y revisar el estado actual.
            </p>
            <h3 style="font-weight: 600; margin-bottom: 16px">
                {{ editingId ? "Editar gasto" : "Nuevo gasto" }}
            </h3>
            <fieldset
                class="f-form-grid f-expense-grid"
                :disabled="embedSaving || form.processing || !editable"
            >
                <FinancialField
                    id="expense-concept"
                    label="Concepto *"
                    v-model="form.concepto"
                    required
                    maxlength="255"
                    :error="form.errors.concepto"
                />
                <div class="f-field">
                    <label for="expense-category">Categoría · opcional</label
                    ><select
                        id="expense-category"
                        class="f-input"
                        v-model="form.expense_category_id"
                        v-bind="
                            errorAttrs(
                                'expense-category',
                                'expense_category_id',
                            )
                        "
                    >
                        <option value="">Sin categoría</option>
                        <option
                            v-for="category in categories"
                            :key="category.id"
                            :value="category.id"
                        >
                            {{ category.nombre }}
                        </option>
                    </select>
                    <p
                        v-if="form.errors.expense_category_id"
                        id="expense-category-error"
                        class="f-field-error"
                        role="alert"
                    >
                        {{ form.errors.expense_category_id }}
                    </p>
                </div>
                <div class="f-field">
                    <label for="expense-currency">Moneda original *</label
                    ><select
                        id="expense-currency"
                        class="f-input"
                        v-model="form.moneda"
                        v-bind="errorAttrs('expense-currency', 'moneda')"
                    >
                        <option :value="trip.moneda_trabajo">
                            {{ trip.moneda_trabajo }}
                        </option>
                        <option value="CLP">CLP</option>
                    </select>
                    <p
                        v-if="form.errors.moneda"
                        id="expense-currency-error"
                        class="f-field-error"
                        role="alert"
                    >
                        {{ form.errors.moneda }}
                    </p>
                </div>
                <FinancialField
                    id="expense-amount"
                    :label="`Monto original · ${form.moneda} *`"
                    v-model="form.monto"
                    inputmode="decimal"
                    required
                    :error="form.errors.monto"
                    help="Por ejemplo 450.000 o 12,50."
                    ><DecimalHint :value="form.monto" :suffix="form.moneda"
                /></FinancialField>
                <div class="f-field">
                    <label for="expense-rate-mode">Conversión *</label
                    ><select
                        id="expense-rate-mode"
                        class="f-input"
                        v-model="form.rate_mode"
                        v-bind="
                            errorAttrs(
                                'expense-rate-mode',
                                'rate_mode',
                                'expense-rate-mode-help',
                            )
                        "
                    >
                        <option value="estimated">
                            Estimado: usar tasa de referencia
                        </option>
                        <option value="paid">
                            Pagado: conservar tasa histórica
                        </option>
                    </select>
                    <p id="expense-rate-mode-help" class="f-hint">
                        {{
                            form.rate_mode === "paid"
                                ? "La tasa del pago se conserva al revaluar la importación."
                                : `La referencia actual es ${formatRate(trip.tipo_cambio)} CLP / ${trip.moneda_trabajo}.`
                        }}
                    </p>
                    <p
                        v-if="form.errors.rate_mode"
                        id="expense-rate-mode-error"
                        class="f-field-error"
                        role="alert"
                    >
                        {{ form.errors.rate_mode }}
                    </p>
                </div>
                <div class="f-field">
                    <label for="expense-method">Distribuir por *</label
                    ><select
                        id="expense-method"
                        class="f-input"
                        v-model="form.allocation_method"
                        v-bind="
                            errorAttrs(
                                'expense-method',
                                'allocation_method',
                                form.allocation_method === 'weight' ||
                                    form.allocation_method === 'volume'
                                    ? 'expense-method-help'
                                    : undefined,
                            )
                        "
                    >
                        <option
                            v-for="(label, value) in rules"
                            :value="value"
                            :key="value"
                        >
                            {{ label }}
                        </option>
                    </select>
                    <p
                        v-if="form.errors.allocation_method"
                        id="expense-method-error"
                        class="f-field-error"
                        role="alert"
                    >
                        {{ form.errors.allocation_method }}
                    </p>
                    <p
                        v-if="
                            form.allocation_method === 'weight' ||
                            form.allocation_method === 'volume'
                        "
                        id="expense-method-help"
                        class="f-hint"
                    >
                        Todas las líneas necesitan su
                        {{
                            form.allocation_method === "weight"
                                ? "peso total en kg"
                                : "volumen total en m³"
                        }}. No se sustituye una medida ausente por cero.
                    </p>
                </div>
                <template v-if="form.rate_mode === 'paid'">
                    <FinancialField
                        id="expense-paid-rate"
                        :label="`Tasa histórica · CLP / ${form.moneda} *`"
                        v-model="form.applied_rate"
                        inputmode="decimal"
                        required
                        :error="form.errors.applied_rate"
                        :help="
                            form.moneda === 'CLP'
                                ? 'Una operación en CLP usa tasa 1.'
                                : `Cuántos pesos te costó 1 ${form.moneda} en ese pago, por ejemplo 950,5.`
                        "
                        ><DecimalHint
                            v-if="form.moneda !== 'CLP'"
                            :value="form.applied_rate"
                            :suffix="`pesos por 1 ${form.moneda}`"
                            :rate-currency="form.moneda"
                    /></FinancialField>
                    <FinancialField
                        id="expense-rate-source"
                        label="Fuente de la tasa *"
                        v-model="form.rate_source"
                        placeholder="Comprobante bancario, factura…"
                        maxlength="255"
                        required
                        :error="form.errors.rate_source"
                    />
                    <FinancialField
                        id="expense-rate-date"
                        label="Fecha del pago *"
                        type="date"
                        v-model="form.rate_date"
                        required
                        :error="form.errors.rate_date"
                    />
                </template>
                <div v-if="form.allocation_method === 'direct'" class="f-field">
                    <label for="expense-target"
                        >Producto que recibe el gasto *</label
                    ><select
                        id="expense-target"
                        class="f-input"
                        v-model="form.target_product_id"
                        required
                        v-bind="
                            errorAttrs('expense-target', 'target_product_id')
                        "
                    >
                        <option value="">Selecciona un producto</option>
                        <option
                            v-for="product in trip.products"
                            :value="product.id"
                            :key="product.id"
                        >
                            {{ product.nombre }}
                        </option>
                    </select>
                    <p
                        v-if="form.errors.target_product_id"
                        id="expense-target-error"
                        class="f-field-error"
                        role="alert"
                    >
                        {{ form.errors.target_product_id }}
                    </p>
                </div>
                <div class="f-field">
                    <label for="expense-receipt">Comprobante · opcional</label>
                    <input
                        id="expense-receipt"
                        class="f-input"
                        type="file"
                        accept="application/pdf,image/jpeg,image/png,image/webp"
                        @change="form.comprobante = inputFile($event)"
                        v-bind="
                            errorAttrs(
                                'expense-receipt',
                                'comprobante',
                                'expense-receipt-help',
                            )
                        "
                    />
                    <p id="expense-receipt-help" class="f-hint">
                        PDF, JPEG, PNG o WebP · hasta 10 MB. Un archivo nuevo
                        reemplaza el vínculo; el anterior se conserva en
                        Documentos.
                    </p>
                    <p v-if="form.comprobante" class="f-hint" role="status">
                        Por adjuntar: {{ form.comprobante.name }}
                    </p>
                    <p
                        v-if="form.errors.comprobante"
                        id="expense-receipt-error"
                        class="f-field-error"
                        role="alert"
                    >
                        {{ form.errors.comprobante }}
                    </p>
                    <template v-if="currentReceipt">
                        <p class="f-hint" style="overflow-wrap: anywhere">
                            Comprobante actual:
                            <a
                                class="f-link"
                                :href="currentReceipt.download_url"
                                >{{ currentReceipt.filename }}</a
                            >
                        </p>
                        <label class="f-check-row" v-if="!form.comprobante"
                            ><input
                                type="checkbox"
                                v-model="form.eliminar_comprobante"
                            />Desvincular el comprobante actual</label
                        >
                        <p
                            v-if="
                                form.eliminar_comprobante && !form.comprobante
                            "
                            class="f-hint"
                        >
                            El archivo seguirá disponible en Documentos.
                        </p>
                    </template>
                </div>
            </fieldset>
            <div
                v-if="Object.keys(form.errors).length"
                ref="errorSummary"
                class="f-notice f-notice-error"
                role="alert"
                tabindex="-1"
                style="margin-top: 12px"
            >
                <p>
                    No se guardó el gasto. Revisa los campos indicados; tu
                    borrador se conserva.
                </p>
                <p v-for="(message, key) in form.errors" :key="key">
                    {{ message }}
                </p>
            </div>
            <div class="f-actions" style="margin-top: 20px">
                <button
                    type="button"
                    class="f-button"
                    @click="close"
                    :disabled="form.processing || embedSaving"
                >
                    Cancelar</button
                ><button
                    type="submit"
                    class="f-button f-button-primary"
                    :disabled="form.processing || embedSaving || !editable"
                >
                    {{
                        form.processing || embedSaving
                            ? "Guardando…"
                            : "Guardar gasto"
                    }}
                </button>
            </div>
        </form>
        <template v-if="trip.expenses.length"
            ><div class="f-table-scroll f-desktop-table">
                <table class="f-table">
                    <caption class="sr-only">
                        Gastos con importe original, conversión y regla de
                        reparto
                    </caption>
                    <thead>
                        <tr>
                            <th scope="col">Gasto</th>
                            <th scope="col">Conversión</th>
                            <th scope="col">Reparto</th>
                            <th scope="col" class="f-numeric">Original</th>
                            <th scope="col" class="f-numeric">Monto · CLP</th>
                            <th scope="col" v-if="editable">
                                <span class="sr-only">Acciones</span>
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="expense in trip.expenses" :key="expense.id">
                            <td>
                                <strong class="f-table-title">{{
                                    expense.concepto
                                }}</strong
                                ><span class="f-table-subtitle">{{
                                    expense.category?.nombre || "Sin categoría"
                                }}</span>
                                <a
                                    v-if="receiptLink(expense)"
                                    class="f-link f-table-subtitle"
                                    :href="receiptLink(expense)"
                                    :aria-label="`Ver comprobante de ${expense.concepto}`"
                                    >Ver comprobante</a
                                >
                            </td>
                            <td v-if="expense.moneda === 'CLP'">
                                <span class="f-table-subtitle">En pesos</span>
                            </td>
                            <td v-else>
                                <span
                                    class="f-status"
                                    :class="{
                                        'f-status-closed':
                                            expense.rate_mode === 'paid',
                                    }"
                                    >{{
                                        expense.rate_mode === "paid"
                                            ? "Pagado"
                                            : "Estimado"
                                    }}</span
                                ><span
                                    v-if="expense.rate_mode === 'paid'"
                                    class="f-table-subtitle"
                                    >{{ formatRate(expense.applied_rate) }} ·
                                    {{
                                        formatDateOnly(expense.rate_date, {
                                            month: "short",
                                        })
                                    }}</span
                                >
                            </td>
                            <td>{{ rules[expense.allocation_method] }}</td>
                            <td class="f-numeric">
                                {{ formatPesos(expense.monto) }}
                                {{ expense.moneda }}
                            </td>
                            <td class="f-numeric">
                                <strong
                                    >${{
                                        formatMoney(expense.monto_clp)
                                    }}</strong
                                >
                            </td>
                            <td v-if="editable">
                                <div class="f-actions">
                                    <button
                                        class="f-icon-button"
                                        @click="edit(expense)"
                                        :disabled="
                                            open ||
                                            embedSaving ||
                                            form.processing
                                        "
                                        :aria-label="`Editar ${expense.concepto}`"
                                    >
                                        <PencilIcon /></button
                                    ><button
                                        class="f-icon-button"
                                        @click="emit('delete', expense)"
                                        :disabled="
                                            open ||
                                            embedSaving ||
                                            form.processing
                                        "
                                        :aria-label="`Eliminar ${expense.concepto}`"
                                    >
                                        <TrashIcon />
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div class="f-mobile-cards">
                <article
                    v-for="expense in trip.expenses"
                    :key="expense.id"
                    class="f-mobile-card"
                >
                    <div class="f-mobile-card-top">
                        <div>
                            <strong class="f-table-title">{{
                                expense.concepto
                            }}</strong
                            ><span class="f-table-subtitle"
                                >{{
                                    expense.category?.nombre || "Sin categoría"
                                }}
                                · {{ rules[expense.allocation_method] }}</span
                            >
                        </div>
                        <span v-if="expense.moneda !== 'CLP'" class="f-status">{{
                            expense.rate_mode === "paid" ? "Pagado" : "Estimado"
                        }}</span>
                    </div>
                    <p
                        v-if="expense.rate_mode === 'paid'"
                        class="f-hint"
                        style="margin-top: 10px; overflow-wrap: anywhere"
                    >
                        Tasa {{ formatRate(expense.applied_rate) }} CLP /
                        {{ expense.moneda }} ·
                        {{
                            formatDateOnly(expense.rate_date, {
                                month: "short",
                            })
                        }}
                    </p>
                    <p v-if="receiptLink(expense)" style="margin-top: 8px">
                        <a
                            class="f-link"
                            :href="receiptLink(expense)"
                            :aria-label="`Ver comprobante de ${expense.concepto}`"
                            >Ver comprobante</a
                        >
                    </p>
                    <div class="f-mobile-card-bottom">
                        <span class="f-hint"
                            >{{ formatPesos(expense.monto) }}
                            {{ expense.moneda }}</span
                        ><strong>${{ formatMoney(expense.monto_clp) }}</strong>
                    </div>
                    <div
                        v-if="editable"
                        class="f-actions"
                        style="margin-top: 12px"
                    >
                        <button
                            class="f-button"
                            @click="edit(expense)"
                            :disabled="open || embedSaving || form.processing"
                        >
                            Editar gasto</button
                        ><button
                            class="f-button f-button-danger"
                            @click="emit('delete', expense)"
                            :disabled="open || embedSaving || form.processing"
                        >
                            Eliminar
                        </button>
                    </div>
                </article>
            </div></template
        >
        <div v-else class="f-empty">
            <h2>Los gastos completan tu costo</h2>
            <p>
                Registra transporte, aduana, flete u otros costos de esta
                importación.
            </p>
            <button
                v-if="editable"
                class="f-button"
                @click="add"
                :disabled="open || embedSaving || form.processing"
            >
                Agregar primer gasto
            </button>
        </div>
    </section>
</template>
