<script setup lang="ts">
import { ref } from "vue";
import { Head, Link, useForm, router } from "@inertiajs/vue3";
import FinancialLayout from "@/Layouts/FinancialLayout.vue";
import FinancialField from "@/Components/UI/FinancialField.vue";
import Modal from "@/Components/UI/Modal.vue";
import { useRoute } from "@/Composables/useRoute";
import { focusInvalid } from "@/Composables/useFormFocus";
import type { ImportConfiguration, NamedRecord } from "@/Types/financial";
const props = defineProps<{
    trip: ImportConfiguration;
    companies: NamedRecord[];
}>();
const route = useRoute();
const form = useForm({
    expected_revision: props.trip.revision,
    nombre: props.trip.nombre,
    descripcion: props.trip.descripcion || "",
    company_id:
        props.trip.company_id === null ? "" : String(props.trip.company_id),
    fecha_inicio: props.trip.fecha_inicio || "",
    fecha_fin: props.trip.fecha_fin || "",
    moneda_trabajo: props.trip.moneda_trabajo,
    tipo_cambio: props.trip.tipo_cambio,
});
const submit = () =>
    form.put(route("trips.update", props.trip.id), {
        onFinish: () => void focusInvalid(),
    });
const confirmTrash = ref(false),
    busy = ref(false),
    trashError = ref("");
const trash = () => {
    if (busy.value) return;
    busy.value = true;
    router.delete(route("trips.destroy", props.trip.id), {
        data: { expected_revision: props.trip.revision },
        onError: (errors) => {
            trashError.value = Object.values(errors).join(" ");
        },
        onSuccess: () => {
            confirmTrash.value = false;
        },
        onFinish: () => {
            busy.value = false;
        },
    });
};
</script>
<template>
    <Head :title="`Configurar ${trip.nombre}`" /><FinancialLayout
        ><Link class="f-back" :href="route('trips.show', trip.id)"
            >← {{ trip.nombre }}</Link
        >
        <div class="f-heading">
            <div>
                <p class="f-eyebrow">
                    IMPORTACIÓN #{{ trip.id }} · REVISIÓN {{ trip.revision }}
                </p>
                <h1>Configurar importación</h1>
                <p>Contexto, fechas y conversión de tu compra.</p>
            </div>
        </div>
        <form
            class="f-editor-form f-config-editor"
            @submit.prevent="submit"
            novalidate
        >
            <section class="f-panel">
                <div class="f-panel-heading"><h2>Información general</h2></div>
                <fieldset
                    class="f-panel-body f-editor-form"
                    :disabled="form.processing || busy"
                >
                    <FinancialField
                        id="nombre"
                        label="Nombre de la importación *"
                        v-model="form.nombre"
                        maxlength="255"
                        required
                        :error="form.errors.nombre"
                    />
                    <FinancialField
                        id="descripcion"
                        label="Descripción · opcional"
                        v-model="form.descripcion"
                        multiline
                        rows="3"
                        maxlength="5000"
                        :error="form.errors.descripcion"
                    />
                    <div class="f-field">
                        <label for="company_id">Empresa · opcional</label
                        ><select
                            id="company_id"
                            class="f-input"
                            v-model="form.company_id"
                            :aria-invalid="!!form.errors.company_id"
                            :aria-describedby="
                                form.errors.company_id
                                    ? 'company_id-error'
                                    : undefined
                            "
                        >
                            <option value="">Sin empresa asignada</option>
                            <option
                                v-for="company in companies"
                                :key="company.id"
                                :value="String(company.id)"
                            >
                                {{ company.nombre }}
                            </option>
                        </select>
                        <p
                            id="company_id-error"
                            role="alert"
                            class="f-field-error"
                            v-if="form.errors.company_id"
                        >
                            {{ form.errors.company_id }}
                        </p>
                    </div>
                    <div class="f-form-grid">
                        <FinancialField
                            id="fecha_inicio"
                            label="Fecha de inicio · opcional"
                            v-model="form.fecha_inicio"
                            type="date"
                            :error="form.errors.fecha_inicio"
                        /><FinancialField
                            id="fecha_fin"
                            label="Fecha de fin · opcional"
                            v-model="form.fecha_fin"
                            type="date"
                            :min="form.fecha_inicio || undefined"
                            :error="form.errors.fecha_fin"
                        />
                    </div>
                </fieldset>
            </section>
            <section class="f-panel">
                <div class="f-panel-heading"><h2>Moneda y conversión</h2></div>
                <fieldset
                    class="f-panel-body f-editor-form"
                    :disabled="form.processing || busy"
                >
                    <p class="f-notice">
                        Al guardar se recalculan los costos estimados con la
                        tasa vigente. Las operaciones pagadas conservan su tasa
                        y monto registrados. Cambiar la moneda exige
                        compatibilidad con las líneas existentes.
                    </p>
                    <div class="f-form-grid">
                        <div class="f-field">
                            <label for="moneda_trabajo"
                                >Moneda de trabajo *</label
                            ><select
                                id="moneda_trabajo"
                                class="f-input"
                                v-model="form.moneda_trabajo"
                                :aria-invalid="!!form.errors.moneda_trabajo"
                                :aria-describedby="
                                    form.errors.moneda_trabajo
                                        ? 'moneda_trabajo-error'
                                        : undefined
                                "
                            >
                                <option value="USD">
                                    USD · dólar estadounidense
                                </option>
                                <option value="EUR">EUR · euro</option>
                                <option value="CNY">CNY · yuan chino</option>
                                <option value="BRL">
                                    BRL · real brasileño
                                </option>
                                <option value="PEN">PEN · sol peruano</option>
                            </select>
                            <p
                                id="moneda_trabajo-error"
                                class="f-field-error"
                                role="alert"
                                v-if="form.errors.moneda_trabajo"
                            >
                                {{ form.errors.moneda_trabajo }}
                            </p>
                        </div>
                        <FinancialField
                            id="tipo_cambio"
                            label="Tasa de referencia a CLP *"
                            v-model="form.tipo_cambio"
                            inputmode="decimal"
                            required
                            :error="form.errors.tipo_cambio"
                            :help="`CLP por 1 ${form.moneda_trabajo} · hasta ocho decimales, con punto`"
                        />
                    </div>
                </fieldset>
            </section>
            <p class="f-hint">
                Esta importación está en curso. Para conservar su revisión y
                bloquear cambios, usa
                <Link
                    class="f-text-link"
                    :href="`${route('trips.show', trip.id)}#revision`"
                    >Revisar cierre</Link
                >.
            </p>
            <div class="f-actions f-config-actions">
                <button
                    type="button"
                    class="f-button f-button-danger"
                    :disabled="form.processing || busy"
                    @click="
                        trashError = '';
                        confirmTrash = true;
                    "
                >
                    Mover a la papelera
                </button>
                <div class="f-actions">
                    <button
                        type="button"
                        class="f-button"
                        :disabled="form.processing || busy"
                        @click="router.visit(route('trips.show', trip.id))"
                    >
                        Cancelar</button
                    ><button
                        class="f-button f-button-primary"
                        :disabled="form.processing || busy"
                    >
                        {{ form.processing ? "Guardando…" : "Guardar cambios" }}
                    </button>
                </div>
            </div>
        </form>
        <Modal
            :show="confirmTrash"
            title="Mover importación a la papelera"
            :closeable="!busy"
            @close="!busy && (confirmTrash = false)"
            ><p>
                «{{ trip.nombre }}» saldrá del Control y la Analítica. Se
                conservarán sus productos, gastos, documentos e historial para
                recuperarla.
            </p>
            <p v-if="trashError" class="f-field-error" role="alert">
                {{ trashError }}
            </p>
            <template #footer
                ><div class="f-actions">
                    <button
                        class="f-button"
                        :disabled="busy"
                        @click="confirmTrash = false"
                    >
                        Cancelar</button
                    ><button
                        class="f-button f-button-danger"
                        :disabled="busy"
                        @click="trash"
                    >
                        {{ busy ? "Moviendo…" : "Mover a la papelera" }}
                    </button>
                </div></template
            ></Modal
        >
    </FinancialLayout>
</template>
