<script setup lang="ts">
import { computed } from "vue";
import { Head, Link } from "@inertiajs/vue3";
import FinancialLayout from "@/Layouts/FinancialLayout.vue";
import { useRoute } from "@/Composables/useRoute";
import { analyticsMoney, formatDateOnly } from "@/Utils/format";
import type { CompanyPage } from "@/Types/catalog";
const props = defineProps<CompanyPage>();
const company = computed(() => props.companyRecord);
const route = useRoute();
const states = {
    activo: "En curso",
    finalizado: "Cerrada",
    archivado: "Archivada",
};
</script>
<template>
    <Head :title="company.nombre" /><FinancialLayout
        ><Link :href="route('companies.index')" class="f-back">← Empresas</Link>
        <div class="f-heading">
            <div>
                <p class="f-eyebrow">RESUMEN DE EMPRESA</p>
                <h1 class="f-company-title">{{ company.nombre }}</h1>
                <p v-if="company.rut">RUT {{ company.rut }}</p>
                <p v-if="company.descripcion">{{ company.descripcion }}</p>
                <span
                    class="f-badge"
                    :class="{ 'is-active': company.activa }"
                    >{{ company.activa ? "Activa" : "Inactiva" }}</span
                >
            </div>
            <Link class="f-button" :href="route('companies.edit', company.id)"
                >Editar empresa</Link
            >
        </div>
        <div
            class="f-metrics f-company-metrics"
            aria-label="Totales de esta empresa"
        >
            <article class="f-metric f-metric-accent">
                <span class="f-metric-label">Inversión total · CLP</span
                ><strong class="f-metric-value"
                    >${{ analyticsMoney(metrics.total) }}</strong
                ><small>Compra + gastos · todas sus importaciones</small>
            </article>
            <article class="f-metric">
                <span class="f-metric-label">Inversión media · CLP</span
                ><strong class="f-metric-value"
                    >${{ analyticsMoney(metrics.average) }}</strong
                ><small
                    >Total / {{ metrics.count }}
                    {{
                        metrics.count === 1 ? "importación" : "importaciones"
                    }}</small
                >
            </article>
            <article class="f-metric">
                <span class="f-metric-label">Importaciones en curso</span
                ><strong class="f-metric-value">{{ metrics.active }}</strong
                ><small
                    >de {{ metrics.count }}
                    {{
                        metrics.count === 1 ? "importación" : "importaciones"
                    }}</small
                >
            </article>
        </div>
        <section class="f-panel">
            <div class="f-panel-heading">
                <h2>Importaciones · {{ trips.total }}</h2>
            </div>
            <template v-if="trips.data.length"
                ><div class="f-table-scroll f-desktop-table">
                    <table class="f-table">
                        <caption class="sr-only">
                            Importaciones de esta empresa, estado e inversión
                        </caption>
                        <thead>
                            <tr>
                                <th scope="col">Importación</th>
                                <th scope="col">Estado</th>
                                <th scope="col">Inicio</th>
                                <th scope="col">Inversión · CLP</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="trip in trips.data" :key="trip.id">
                                <td>
                                    <Link
                                        class="f-table-title"
                                        :href="route('trips.show', trip.id)"
                                        >{{ trip.nombre }}</Link
                                    ><span class="f-table-subtitle"
                                        >{{ trip.products_count }}
                                        {{
                                            trip.products_count === 1
                                                ? "línea"
                                                : "líneas"
                                        }}
                                        · {{ trip.expenses_count }}
                                        {{
                                            trip.expenses_count === 1
                                                ? "gasto"
                                                : "gastos"
                                        }}</span
                                    >
                                </td>
                                <td>
                                    <span
                                        class="f-status"
                                        :class="{
                                            'f-status-closed':
                                                trip.estado !== 'activo',
                                        }"
                                        >{{ states[trip.estado] }}</span
                                    >
                                </td>
                                <td>
                                    {{
                                        formatDateOnly(trip.fecha_inicio, {
                                            month: "short",
                                        })
                                    }}
                                </td>
                                <td class="f-numeric">
                                    ${{ analyticsMoney(trip.costo_total) }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div class="f-mobile-cards">
                    <article
                        class="f-mobile-card"
                        v-for="trip in trips.data"
                        :key="trip.id"
                    >
                        <div class="f-mobile-card-top">
                            <div>
                                <Link
                                    class="f-table-title"
                                    :href="route('trips.show', trip.id)"
                                    >{{ trip.nombre }}</Link
                                ><span class="f-table-subtitle"
                                    >{{
                                        formatDateOnly(trip.fecha_inicio, {
                                            month: "short",
                                        })
                                    }}
                                    · {{ trip.products_count }} líneas</span
                                >
                            </div>
                            <span class="f-status">{{
                                states[trip.estado]
                            }}</span>
                        </div>
                        <div class="f-mobile-card-bottom">
                            <span class="f-hint">INVERSIÓN · CLP</span
                            ><strong
                                >${{ analyticsMoney(trip.costo_total) }}</strong
                            >
                        </div>
                    </article>
                </div>
                <div class="f-pagination">
                    <span
                        >{{ trips.from }}–{{ trips.to }} de
                        {{ trips.total }}</span
                    >
                    <nav
                        class="f-pagination-links"
                        aria-label="Páginas de importaciones de empresa"
                    >
                        <Link
                            v-if="trips.prev_page_url"
                            class="f-button"
                            :href="trips.prev_page_url"
                            preserve-scroll
                            >Anterior</Link
                        ><span class="f-hint"
                            >{{ trips.current_page }} /
                            {{ trips.last_page }}</span
                        ><Link
                            v-if="trips.next_page_url"
                            class="f-button"
                            :href="trips.next_page_url"
                            preserve-scroll
                            >Siguiente</Link
                        >
                    </nav>
                </div></template
            >
            <div class="f-empty" v-else>
                <h2>
                    {{
                        trips.total
                            ? "Esta página no tiene importaciones"
                            : "Sin importaciones asignadas"
                    }}
                </h2>
                <p>
                    {{
                        trips.total
                            ? "Vuelve a la primera página para continuar."
                            : "Asigna esta empresa al crear o configurar una importación."
                    }}
                </p>
                <Link
                    v-if="trips.total"
                    class="f-button"
                    :href="route('companies.show', company.id)"
                    >Primera página</Link
                ><Link v-else class="f-button" :href="route('trips.index')"
                    >Control de importaciones</Link
                >
            </div>
        </section>
        <p class="f-footer-note">
            Este resumen abarca esta empresa, independientemente del selector
            activo. Excluye la papelera y conserva los importes registrados. No
            representa ventas realizadas.
        </p></FinancialLayout
    >
</template>
