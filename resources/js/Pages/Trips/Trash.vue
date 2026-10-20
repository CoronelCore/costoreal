<script setup>
import { inject, ref } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import FinancialLayout from '@/Layouts/FinancialLayout.vue';
import Modal from '@/Components/UI/Modal.vue';
import { count, formatMoney } from '@/Utils/format';

const props = defineProps({ trips:Object, filters:Object });
const route = inject('route');
const search = ref(props.filters?.q || '');
const selected = ref(null);
const form = useForm({});
const status = trip => ({activo:'En curso',finalizado:'Cerrada',archivado:'Archivada'})[trip.estado] || trip.estado;
const date = value => new Date(value).toLocaleString('es-CL');
const filter = () => router.get(route('trips.trash'),search.value ? {q:search.value} : {},{preserveState:true,preserveScroll:true,replace:true});
const askRestore = trip => { selected.value = trip; form.clearErrors(); };
const restore = () => form.transform(()=>({expected_revision:selected.value.revision})).post(route('trips.restore',selected.value.id),{onSuccess:()=>selected.value = null});
</script>

<template>
    <Head title="Papelera de importaciones" />
    <FinancialLayout>
        <Link :href="route('trips.index')" class="f-hint">← Control de importaciones</Link>
        <div class="f-heading" style="margin-top:20px"><div><div class="f-eyebrow">RECUPERACIÓN</div><h1>Papelera de importaciones</h1><p>Recupera una importación con sus costos y documentos originales.</p></div></div>
        <div class="f-notice">Las importaciones de la papelera quedan fuera del Control y la Analítica. Sus productos, gastos, documentos, escenarios y revisiones se conservan. Restaurar mantiene el estado previo, incluidas las importaciones cerradas. No hay eliminación automática.</div>
        <section class="f-panel"><div class="f-panel-heading"><h2>Importaciones conservadas · {{ trips.total }}</h2></div>
            <form class="f-panel-body f-actions" @submit.prevent="filter"><div class="f-field" style="flex:1;min-width:0"><label for="trash-search">Buscar en la papelera</label><input id="trash-search" class="f-input" type="search" v-model="search" maxlength="150" placeholder="Nombre de la importación" /></div><button class="f-button" type="submit">Buscar</button><button v-if="search" class="f-button" type="button" @click="search = ''; filter()">Limpiar</button></form>
            <template v-if="trips.data.length">
                <div class="f-table-scroll f-desktop-table"><table class="f-table"><caption class="sr-only">Importaciones recuperables y costos conservados</caption><thead><tr><th>Importación</th><th>Estado previo</th><th class="f-numeric">Costo conservado · CLP</th><th>Contenido</th><th><span class="sr-only">Restaurar</span></th></tr></thead><tbody><tr v-for="trip in trips.data" :key="trip.id"><td><strong class="f-table-title">{{ trip.nombre }}</strong><span class="f-table-subtitle">{{ trip.company?.nombre || 'Sin empresa' }} · revisión {{ trip.revision }}<br />Papelera: {{ date(trip.deleted_at) }}</span></td><td><span class="f-status f-status-closed">{{ status(trip) }}</span></td><td class="f-numeric">${{ formatMoney(trip.costo_total) }}</td><td><span class="f-hint">{{ count(trip.products_count, 'producto', 'productos') }} · {{ count(trip.expenses_count, 'gasto', 'gastos') }}<br />{{ trip.documents_count }} documentos activos</span></td><td><button class="f-button" @click="askRestore(trip)" :aria-label="`Restaurar ${trip.nombre}`">Restaurar</button></td></tr></tbody></table></div>
                <div class="f-mobile-cards"><article v-for="trip in trips.data" :key="trip.id" class="f-mobile-card"><strong>{{ trip.nombre }}</strong><p class="f-hint">{{ trip.company?.nombre || 'Sin empresa' }} · revisión {{ trip.revision }}</p><p class="f-hint">Papelera: {{ date(trip.deleted_at) }}</p><div class="f-mobile-card-bottom"><span>Estado previo</span><span class="f-status f-status-closed">{{ status(trip) }}</span></div><div class="f-mobile-card-bottom"><span>Costo conservado</span><strong>${{ formatMoney(trip.costo_total) }}</strong></div><p class="f-hint" style="margin:12px 0">{{ count(trip.products_count, 'producto', 'productos') }} · {{ count(trip.expenses_count, 'gasto', 'gastos') }} · {{ trip.documents_count }} documentos activos</p><button class="f-button" @click="askRestore(trip)" :aria-label="`Restaurar ${trip.nombre}`">Restaurar importación</button></article></div>
                <div class="f-pagination"><span>{{ trips.from }}–{{ trips.to }} de {{ trips.total }}</span><div class="f-actions"><Link v-if="trips.prev_page_url" :href="trips.prev_page_url" class="f-button">Anteriores</Link><span>{{ trips.current_page }} / {{ trips.last_page }}</span><Link v-if="trips.next_page_url" :href="trips.next_page_url" class="f-button">Siguientes</Link></div></div>
            </template>
            <div v-else class="f-empty"><h2>{{ search ? 'Sin coincidencias' : 'La papelera está vacía' }}</h2><p>{{ search ? 'Prueba con otro nombre o limpia la búsqueda.' : 'Las importaciones que envíes a la papelera aparecerán aquí para recuperarlas.' }}</p><Link :href="route('trips.index')" class="f-button">Volver al Control</Link></div>
        </section>
        <Modal :show="!!selected" title="Restaurar importación" :closeable="!form.processing" @close="selected = null"><p v-if="selected">«{{ selected.nombre }}» volverá al Control con estado «{{ status(selected) }}» y costo conservado de ${{ formatMoney(selected.costo_total) }}. Sus documentos e historial seguirán disponibles. Se registrará la restauración como una nueva revisión.</p><div v-if="Object.keys(form.errors).length" class="f-notice f-notice-error" role="alert" style="margin-top:16px"><p v-for="(message,key) in form.errors" :key="key">{{ message }}</p><button v-if="form.errors.conflict" class="f-button" @click="selected = null; router.reload()">Recargar papelera</button></div><div class="f-actions" style="margin-top:20px"><button class="f-button" @click="selected = null" :disabled="form.processing">Cancelar</button><button class="f-button f-button-primary" @click="restore" :disabled="form.processing">{{ form.processing ? 'Restaurando…' : 'Restaurar importación' }}</button></div></Modal>
    </FinancialLayout>
</template>
