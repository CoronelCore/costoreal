<script setup lang="ts">
import { computed, ref } from "vue";
import { Head, Link, router } from "@inertiajs/vue3";
import FinancialLayout from "@/Layouts/FinancialLayout.vue";
import Modal from "@/Components/UI/Modal.vue";
import CompanyIcon from "@/Components/Domain/CompanyIcon.vue";
import CategoryIcon from "@/Components/Domain/CategoryIcon.vue";
import { useRoute } from "@/Composables/useRoute";
import type { CatalogRecord } from "@/Types/catalog";
const props = defineProps<{
    kind: "company" | "category";
    records: CatalogRecord[];
}>();
const company = props.kind === "company";
const route = useRoute();
const resource = company ? "companies" : "categories";
const query = ref("");
const title = company ? "Empresas" : "Categorías de gastos";
const selected = ref<CatalogRecord | null>(null);
const busy = ref(false);
const error = ref("");
const filtered = computed(() =>
    props.records.filter((r) =>
        `${r.nombre} ${r.rut || ""}`
            .toLocaleLowerCase("es")
            .includes(query.value.toLocaleLowerCase("es")),
    ),
);
const removable = (r: CatalogRecord) =>
    company ? r.can_delete === true : r.editable === true && !r.expenses_count;
const askRemove = (r: CatalogRecord) => {
    error.value = "";
    selected.value = r;
};
const remove = () => {
    if (!selected.value || busy.value) return;
    busy.value = true;
    router.delete(route(`${resource}.destroy`, selected.value.id), {
        preserveScroll: true,
        onError: (errors) => {
            error.value = Object.values(errors).join(" ");
        },
        onSuccess: () => {
            selected.value = null;
        },
        onFinish: () => {
            busy.value = false;
        },
    });
};
</script>
<template>
    <Head :title="title" /><FinancialLayout
        ><div class="f-heading">
            <div>
                <p class="f-eyebrow">
                    {{
                        company
                            ? "TU ESPACIO DE TRABAJO"
                            : "UN LENGUAJE COMÚN PARA TUS COSTOS"
                    }}
                </p>
                <h1>{{ title }}</h1>
                <p>
                    {{
                        company
                            ? "Organiza tus importaciones por empresa."
                            : "Clasifica tus gastos y revisa en qué se invierte cada peso."
                    }}
                </p>
            </div>
            <Link
                class="f-button f-button-primary"
                :href="route(`${resource}.create`)"
                >{{ company ? "Nueva empresa" : "Nueva categoría" }}</Link
            >
        </div>
        <section class="f-panel">
            <div class="f-panel-heading">
                <h2>
                    {{
                        company
                            ? "Empresas registradas"
                            : "Categorías disponibles"
                    }}
                    · {{ records.length }}
                </h2>
            </div>
            <div class="f-panel-body">
                <div class="f-field f-catalog-search">
                    <label for="catalog-search">{{
                        company ? "Buscar empresa" : "Buscar categoría"
                    }}</label
                    ><input
                        id="catalog-search"
                        type="search"
                        class="f-input"
                        v-model="query"
                    />
                    <p role="status" class="f-hint">
                        {{ filtered.length }}
                        {{ filtered.length === 1 ? "resultado" : "resultados" }}
                    </p>
                </div>
                <div class="f-catalog-grid" v-if="filtered.length">
                    <article
                        v-for="record in filtered"
                        :key="record.id"
                        class="f-catalog-card"
                    >
                        <div class="f-record-head">
                            <span class="f-record-icon"
                                ><CompanyIcon
                                    v-if="company"
                                    :icon="record.icono || 'BuildingOfficeIcon'"
                                    :color="record.color || 'currentColor'"
                                    size="lg"
                                    aria-hidden="true" /><CategoryIcon
                                    v-else
                                    :icon="record.icono || 'dots-horizontal'"
                                    :color="record.color || 'currentColor'"
                                    size="lg"
                                    aria-hidden="true"
                            /></span>
                            <div>
                                <h3>{{ record.nombre }}</h3>
                                <p class="f-hint" v-if="company && record.rut">
                                    RUT {{ record.rut }}
                                </p>
                                <span
                                    class="f-badge"
                                    :class="{
                                        'is-active': company && record.activa,
                                    }"
                                    >{{
                                        company
                                            ? record.activa
                                                ? "Activa"
                                                : "Inactiva"
                                            : record.editable
                                              ? "Personal"
                                              : "Base · solo lectura"
                                    }}</span
                                >
                            </div>
                        </div>
                        <p
                            class="f-hint f-record-description"
                            v-if="company && record.descripcion"
                        >
                            {{ record.descripcion }}
                        </p>
                        <p class="f-record-count">
                            {{
                                company
                                    ? record.trips_count
                                    : record.expenses_count
                            }}
                            {{ company ? "importaciones" : "gastos" }}
                        </p>
                        <div class="f-actions">
                            <Link
                                v-if="company"
                                class="f-button"
                                :href="route('companies.show', record.id)"
                                :aria-label="`Ver ${record.nombre}`"
                                >Ver</Link
                            ><Link
                                v-if="company || record.editable"
                                class="f-button"
                                :href="route(`${resource}.edit`, record.id)"
                                :aria-label="`Editar ${record.nombre}`"
                                >Editar</Link
                            ><button
                                v-if="company || record.editable"
                                class="f-button f-button-danger"
                                :disabled="!removable(record)"
                                :aria-label="`Eliminar ${record.nombre}`"
                                @click="askRemove(record)"
                            >
                                Eliminar
                            </button>
                        </div>
                        <p v-if="company && !record.can_delete" class="f-hint">
                            Conserva importaciones vinculadas, incluidas las de
                            la papelera.
                        </p>
                        <p
                            v-if="
                                !company &&
                                record.editable &&
                                record.expenses_count
                            "
                            class="f-hint"
                        >
                            Reasigna sus gastos antes de eliminar esta
                            categoría.
                        </p>
                    </article>
                </div>
                <div class="f-empty" v-else>
                    <h2>
                        {{
                            records.length
                                ? "No hay coincidencias"
                                : company
                                  ? "Tu primera empresa"
                                  : "Organiza tus gastos"
                        }}
                    </h2>
                    <p>
                        {{
                            records.length
                                ? "Prueba con otro nombre o limpia la búsqueda."
                                : company
                                  ? "Puedes comenzar sin empresa y organizar tus importaciones después."
                                  : "Crea una categoría personal para clasificar tus gastos."
                        }}
                    </p>
                    <button v-if="query" class="f-button" @click="query = ''">
                        Limpiar búsqueda</button
                    ><Link
                        v-else
                        :href="route(`${resource}.create`)"
                        class="f-button f-button-primary"
                        >{{
                            company ? "Crear empresa" : "Crear categoría"
                        }}</Link
                    >
                </div>
            </div>
        </section>
        <Modal
            :show="!!selected"
            :title="company ? 'Eliminar empresa' : 'Eliminar categoría'"
            :closeable="!busy"
            @close="!busy && (selected = null)"
            ><p>
                Se eliminará «{{ selected?.nombre }}». Esta acción no puede
                deshacerse. Solo se permite cuando no hay
                {{ company ? "importaciones vinculadas" : "gastos asociados" }}.
            </p>
            <p v-if="error" class="f-field-error" role="alert">{{ error }}</p>
            <template #footer
                ><div class="f-actions">
                    <button
                        class="f-button"
                        :disabled="busy"
                        @click="selected = null"
                    >
                        Cancelar</button
                    ><button
                        class="f-button f-button-danger"
                        :disabled="busy"
                        @click="remove"
                    >
                        {{ busy ? "Eliminando…" : "Eliminar" }}
                    </button>
                </div></template
            ></Modal
        >
    </FinancialLayout>
</template>
