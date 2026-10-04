<script setup lang="ts">
import { computed } from "vue";
import { Head, router, useForm } from "@inertiajs/vue3";
import FinancialLayout from "@/Layouts/FinancialLayout.vue";
import FinancialField from "@/Components/UI/FinancialField.vue";
import CompanyIcon from "@/Components/Domain/CompanyIcon.vue";
import CategoryIcon from "@/Components/Domain/CategoryIcon.vue";
import { useRoute } from "@/Composables/useRoute";
import { focusInvalid } from "@/Composables/useFormFocus";
import type { CatalogRecord, CatalogMutation } from "@/Types/catalog";
const props = defineProps<{
    kind: "company" | "category";
    record?: CatalogRecord;
}>();
const route = useRoute();
const company = props.kind === "company";
const resource = company ? "companies" : "categories";
const title = computed(
    () =>
        `${props.record ? "Editar" : "Nueva"} ${company ? "empresa" : "categoría"}`,
);
const form = useForm<CatalogMutation>({
    nombre: props.record?.nombre || "",
    rut: props.record?.rut || "",
    descripcion: props.record?.descripcion || "",
    color: props.record?.color || "#2f615b",
    icono: props.record?.icono || (company ? "BuildingOfficeIcon" : "truck"),
    activa: props.record?.activa ?? true,
});
const iconComponent = company ? CompanyIcon : CategoryIcon;
const companyIcons = [
    ["BuildingOfficeIcon", "Empresa"],
    ["TruckIcon", "Transporte"],
    ["ShoppingCartIcon", "Compra"],
    ["GlobeAltIcon", "Internacional"],
    ["CubeIcon", "Productos"],
    ["SparklesIcon", "Especial"],
    ["RocketLaunchIcon", "Desarrollo"],
    ["BriefcaseIcon", "Negocio"],
    ["HomeModernIcon", "Local"],
    ["BeakerIcon", "Laboratorio"],
    ["Cog6ToothIcon", "Servicio"],
    ["LightBulbIcon", "Ideas"],
];
const categoryIcons = [
    ["truck", "Transporte"],
    ["home", "Alojamiento"],
    ["cake", "Alimentación"],
    ["paper-airplane", "Envío"],
    ["location-marker", "Localización"],
    ["cash", "Pago"],
    ["lightning-bolt", "Servicio"],
    ["dots-horizontal", "Otros"],
];
const icons = computed(() => {
    const choices = company ? companyIcons : categoryIcons;
    return choices.some(([value]) => value === form.icono)
        ? choices
        : [...choices, [form.icono, "Icono actual"]];
});
const colors = [
    ["#2f615b", "Petróleo"],
    ["#426d8c", "Azul"],
    ["#71628c", "Violeta"],
    ["#94624b", "Terracota"],
    ["#7a743d", "Oliva"],
    ["#9b535e", "Bordó"],
    ["#52647a", "Pizarra"],
    ["#556f57", "Verde"],
];
const submit = () => {
    const options = { onFinish: () => void focusInvalid() };
    if (props.record)
        form.put(route(`${resource}.update`, props.record.id), options);
    else form.post(route(`${resource}.store`), options);
};
</script>
<template>
    <Head :title="title" /><FinancialLayout>
        <Link class="f-back" :href="route(`${resource}.index`)"
            >← {{ company ? "Empresas" : "Categorías" }}</Link
        >
        <div class="f-heading">
            <div>
                <p class="f-eyebrow">
                    {{
                        company
                            ? "ORGANIZA TU OPERACIÓN"
                            : "CLASIFICA TUS GASTOS"
                    }}
                </p>
                <h1>{{ title }}</h1>
                <p>
                    {{
                        company
                            ? "Un contexto claro para tus importaciones."
                            : "Una clasificación consistente para revisar tus costos."
                    }}
                </p>
            </div>
        </div>
        <form class="f-catalog-editor" @submit.prevent="submit" novalidate>
            <div class="f-editor-form">
                <section class="f-panel">
                    <div class="f-panel-heading">
                        <h2>
                            Información
                            {{ company ? "de la empresa" : "de la categoría" }}
                        </h2>
                    </div>
                    <fieldset
                        class="f-panel-body f-editor-form"
                        :disabled="form.processing"
                    >
                        <FinancialField
                            id="catalog-name"
                            label="Nombre *"
                            v-model="form.nombre"
                            maxlength="255"
                            required
                            :error="form.errors.nombre"
                        />
                        <FinancialField
                            v-if="company"
                            id="catalog-rut"
                            label="RUT · opcional"
                            v-model="form.rut"
                            maxlength="255"
                            :error="form.errors.rut"
                        />
                        <FinancialField
                            v-if="company"
                            id="catalog-description"
                            label="Descripción · opcional"
                            v-model="form.descripcion"
                            multiline
                            rows="3"
                            :error="form.errors.descripcion"
                        />
                        <label v-if="company && record" class="f-check"
                            ><input type="checkbox" v-model="form.activa" />
                            Empresa activa</label
                        >
                        <p v-if="company && record" class="f-hint">
                            Una empresa inactiva conserva sus importaciones,
                            pero no aparece en el selector de empresa activa.
                        </p>
                    </fieldset>
                </section>
                <section class="f-panel">
                    <div class="f-panel-heading"><h2>Identidad visual</h2></div>
                    <fieldset
                        class="f-panel-body f-editor-form"
                        :disabled="form.processing"
                    >
                        <div class="f-form-grid">
                            <FinancialField
                                id="catalog-color"
                                label="Color · hexadecimal *"
                                v-model="form.color"
                                maxlength="7"
                                pattern="#[0-9a-fA-F]{6}"
                                required
                                :error="form.errors.color"
                                help="Ejemplo: #2f615b"
                            />
                            <div class="f-field">
                                <label for="catalog-color-picker"
                                    >Elegir color</label
                                ><input
                                    id="catalog-color-picker"
                                    type="color"
                                    class="f-color-input"
                                    v-model="form.color"
                                />
                            </div>
                        </div>
                        <div
                            class="f-color-choices"
                            role="group"
                            aria-label="Colores sugeridos"
                        >
                            <button
                                v-for="[value, label] in colors"
                                :key="value"
                                type="button"
                                class="f-color-choice"
                                :aria-label="`Color ${label}`"
                                :aria-pressed="
                                    form.color.toLowerCase() === value
                                "
                                @click="form.color = value"
                            >
                                <span :style="{ backgroundColor: value }"></span
                                >{{ label }}
                            </button>
                        </div>
                        <div>
                            <h3 class="f-field-title">Icono</h3>
                            <div
                                class="f-icon-choices"
                                role="group"
                                aria-label="Icono"
                            >
                                <button
                                    v-for="[value, label] in icons"
                                    :key="value"
                                    type="button"
                                    class="f-icon-choice"
                                    :aria-pressed="form.icono === value"
                                    @click="form.icono = value"
                                >
                                    <component
                                        :is="iconComponent"
                                        :icon="value"
                                        color="currentColor"
                                        size="md"
                                        aria-hidden="true"
                                    /><span>{{ label }}</span>
                                </button>
                            </div>
                            <p
                                class="f-field-error"
                                role="alert"
                                v-if="form.errors.icono"
                            >
                                {{ form.errors.icono }}
                            </p>
                        </div>
                    </fieldset>
                </section>
                <div class="f-actions">
                    <button
                        type="button"
                        class="f-button"
                        :disabled="form.processing"
                        @click="router.visit(route(`${resource}.index`))"
                    >
                        Cancelar</button
                    ><button
                        class="f-button f-button-primary"
                        :disabled="form.processing"
                    >
                        {{
                            form.processing
                                ? "Guardando…"
                                : record
                                  ? "Guardar cambios"
                                  : company
                                    ? "Crear empresa"
                                    : "Crear categoría"
                        }}
                    </button>
                </div>
            </div>
            <aside class="f-panel f-record-preview">
                <div class="f-panel-heading"><h2>Vista previa</h2></div>
                <div class="f-panel-body">
                    <span class="f-record-icon"
                        ><component
                            :is="iconComponent"
                            :icon="form.icono"
                            :color="
                                /^#[0-9a-f]{6}$/i.test(form.color)
                                    ? form.color
                                    : 'currentColor'
                            "
                            size="lg"
                            aria-hidden="true"
                    /></span>
                    <h2>
                        {{
                            form.nombre ||
                            (company ? "Tu empresa" : "Tu categoría")
                        }}
                    </h2>
                    <p v-if="company && form.rut" class="f-hint">
                        RUT {{ form.rut }}
                    </p>
                    <p v-if="company && form.descripcion" class="f-hint">
                        {{ form.descripcion }}
                    </p>
                    <p class="f-hint">
                        {{
                            company
                                ? "Agrupa importaciones sin mezclar sus costos."
                                : "Disponible para clasificar tus gastos."
                        }}
                    </p>
                </div>
            </aside>
        </form></FinancialLayout
    >
</template>
