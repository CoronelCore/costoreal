<script setup lang="ts">
import { reactive } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import FinancialLayout from '@/Layouts/FinancialLayout.vue';
import { useRoute } from '@/Composables/useRoute';
import type { Pagination, ImportListItem, ControlMetrics, ControlFilters } from '@/Types/financial';
import { formatMoney, formatDateOnly } from '@/Utils/format';
import { PlusIcon, ArrowRightIcon, MagnifyingGlassIcon } from '@heroicons/vue/24/outline';
const route = useRoute();
const props = defineProps<{ trips: Pagination<ImportListItem>; metrics: ControlMetrics; filters: ControlFilters }>();
const filters = reactive({ q: props.filters?.q || '', state: props.filters?.state || '', sort: props.filters?.sort || 'latest', from: props.filters?.from || '', to: props.filters?.to || '' });
const applyFilters = () => router.get(route('trips.index'), Object.fromEntries(Object.entries(filters).filter(([, value]) => value !== '')), { preserveState: true, preserveScroll: true, replace: true });
const clearFilters = () => { Object.assign(filters, { q: '', state: '', sort: 'latest', from: '', to: '' }); applyFilters(); };
const status = (trip: ImportListItem) => ({ activo: 'En curso', finalizado: 'Cerrada', archivado: 'Archivada' })[trip.estado];
const pending = (trip: ImportListItem) => trip.products_count === 0 ? 'Agregar productos' : trip.priced_count < trip.products_count ? `${trip.products_count - trip.priced_count} precios pendientes` : 'Precios completos';
const pendingTab = (trip: ImportListItem) => trip.products_count === 0 ? 'productos' : 'precio-venta';
</script>
<template>
    <Head title="Control de importaciones" />
    <FinancialLayout>
        <div class="f-heading"><div><div class="f-eyebrow">TU OPERACIÓN, EN CIFRAS</div><h1>Control de importaciones</h1><p>Del precio de compra al costo final. Cada peso, en su lugar.</p></div><div class="f-actions"><Link :href="route('trips.create')" class="f-button f-button-primary"><PlusIcon /> Nueva importación</Link></div></div>
        <div class="f-metrics" aria-label="Totales de todas las importaciones del filtro">
            <article class="f-metric f-metric-accent"><span class="f-metric-label">Inversión total · CLP</span><strong class="f-metric-value">${{ formatMoney(metrics.total) }}</strong><small>Compra + gastos del filtro completo</small></article>
            <article class="f-metric"><span class="f-metric-label">Compra de productos · CLP</span><strong class="f-metric-value">${{ formatMoney(metrics.purchase) }}</strong><small>Conversión aplicada a cada línea</small></article>
            <article class="f-metric"><span class="f-metric-label">Gastos de importación · CLP</span><strong class="f-metric-value">${{ formatMoney(metrics.expenses) }}</strong><small>Incluye gastos aún sin distribuir</small></article>
            <article class="f-metric"><span class="f-metric-label">Importaciones en curso</span><strong class="f-metric-value">{{ metrics.active }} <span style="font-size:13px;font-weight:400;color:var(--f-muted)">de {{ metrics.count }}</span></strong><small>{{ metrics.priced }} / {{ metrics.products }} líneas con precio de venta</small></article>
        </div>
        <section class="f-panel" aria-labelledby="imports-heading">
            <div class="f-panel-heading"><h2 id="imports-heading">Tus importaciones <span class="f-muted">· {{ trips.total }}</span></h2><div class="f-actions"><span class="f-hint">{{ metrics.active }} en curso · moneda base CLP</span><Link :href="route('trips.trash')" class="f-button">Papelera</Link></div></div>
            <form class="f-panel-body f-filters" @submit.prevent="applyFilters">
                <div class="f-field f-search-field"><label for="search">Buscar importación</label><input class="f-input" id="search" v-model="filters.q" type="search" placeholder="Nombre de la importación" /></div>
                <div class="f-field"><label for="state">Estado</label><select class="f-input" id="state" v-model="filters.state" @change="applyFilters"><option value="">Todos los estados</option><option value="activo">En curso</option><option value="finalizado">Cerradas</option><option value="archivado">Archivadas</option></select></div>
                <div class="f-field"><label for="sort">Ordenar por</label><select class="f-input" id="sort" v-model="filters.sort" @change="applyFilters"><option value="latest">Más recientes</option><option value="cost">Mayor inversión</option><option value="name">Nombre</option></select></div>
                <div class="f-field"><label for="from">Desde</label><input class="f-input" id="from" v-model="filters.from" type="date" /></div><div class="f-field"><label for="to">Hasta</label><input class="f-input" id="to" v-model="filters.to" type="date" /></div>
                <button class="f-button" type="submit"><MagnifyingGlassIcon /> Filtrar</button><button v-if="filters.q || filters.state || filters.from || filters.to" class="f-button" type="button" @click="clearFilters">Limpiar</button>
            </form>
            <template v-if="trips.data.length">
                <div class="f-table-scroll f-desktop-table"><table class="f-table"><caption class="sr-only">Importaciones autorizadas, costos y pendientes</caption><thead><tr><th scope="col">Importación</th><th scope="col">Estado</th><th scope="col">Productos</th><th scope="col" class="f-numeric">Compra · CLP</th><th scope="col" class="f-numeric">Gastos · CLP</th><th scope="col" class="f-numeric">Inversión · CLP</th><th scope="col">Pendiente</th><th scope="col"><span class="sr-only">Abrir</span></th></tr></thead><tbody><tr v-for="trip in trips.data" :key="trip.id"><td><span class="f-table-title"><Link :href="route('trips.show', trip.id)">{{ trip.nombre }}</Link></span><span class="f-table-subtitle">{{ trip.company?.nombre || 'Sin empresa' }} · {{ formatDateOnly(trip.fecha_inicio, { month: 'short' }) }}</span></td><td><span class="f-status" :class="{ 'f-status-closed': trip.estado !== 'activo' }">{{ status(trip) }}</span></td><td>{{ trip.products_count }} líneas</td><td class="f-numeric">${{ formatMoney(trip.total_productos_clp ?? '0') }}</td><td class="f-numeric">${{ formatMoney(trip.total_gastos_clp ?? '0') }}</td><td class="f-numeric"><strong>${{ formatMoney(trip.costo_total) }}</strong></td><td><Link :href="`${route('trips.show', trip.id)}#${pendingTab(trip)}`" class="f-hint">{{ pending(trip) }}</Link></td><td><Link :href="route('trips.show', trip.id)" class="f-icon-button" :aria-label="`Abrir ${trip.nombre}`"><ArrowRightIcon /></Link></td></tr></tbody></table></div>
                <div class="f-mobile-cards"><article v-for="trip in trips.data" :key="trip.id" class="f-mobile-card"><div class="f-mobile-card-top"><div><Link :href="route('trips.show', trip.id)" class="f-table-title">{{ trip.nombre }}</Link><span class="f-table-subtitle">{{ trip.company?.nombre || 'Sin empresa' }} · {{ trip.products_count }} líneas</span></div><span class="f-status" :class="{ 'f-status-closed': trip.estado !== 'activo' }">{{ status(trip) }}</span></div><div class="f-mobile-card-bottom"><div><span class="f-table-subtitle">INVERSIÓN · CLP</span><strong>${{ formatMoney(trip.costo_total) }}</strong></div><Link :href="`${route('trips.show', trip.id)}#${pendingTab(trip)}`" class="f-hint">{{ pending(trip) }} →</Link></div></article></div>
                <div class="f-pagination"><span>{{ trips.from }}–{{ trips.to }} de {{ trips.total }} importaciones</span><nav class="f-pagination-links" aria-label="Páginas de importaciones"><Link v-if="trips.prev_page_url" :href="trips.prev_page_url" class="f-button" preserve-scroll>Anterior</Link><span class="f-hint" style="padding:8px">{{ trips.current_page }} / {{ trips.last_page }}</span><Link v-if="trips.next_page_url" :href="trips.next_page_url" class="f-button" preserve-scroll>Siguiente</Link></nav></div>
            </template>
            <div v-else class="f-empty"><h2>{{ filters.q || filters.state || filters.from || filters.to ? 'Sin resultados para este filtro' : 'Tu próxima importación empieza aquí' }}</h2><p>{{ filters.q || filters.state || filters.from || filters.to ? 'Prueba otro nombre, período o estado para encontrar tus importaciones.' : 'Registra la compra, incorpora sus gastos y conoce el costo final de cada producto.' }}</p><button v-if="filters.q || filters.state || filters.from || filters.to" class="f-button" @click="clearFilters">Limpiar filtros</button><Link v-else :href="route('trips.create')" class="f-button f-button-primary"><PlusIcon /> Crear primera importación</Link></div>
        </section>
        <p class="f-footer-note">Los indicadores abarcan todas las importaciones del filtro y de la empresa seleccionada. El precio de venta representa una proyección; la utilidad realizada requiere registrar ventas reales.</p>
    </FinancialLayout>
</template>
