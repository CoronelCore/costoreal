<script setup lang="ts">
import { onBeforeUnmount, onMounted, ref, watch, nextTick } from 'vue';
import axios from 'axios';
import { useRoute } from '@/Composables/useRoute';
import type { CostRunHistory, ImportDetail } from '@/Types/financial';
const props = defineProps<{ trip: ImportDetail }>();
const route = useRoute();
const listing = ref<CostRunHistory>({ data:[], total:0, from:null, to:null, current_page:1, last_page:1, prev_page_url:null, next_page_url:null, anchor:null, reason:null, revision:null });
const reason = ref('');
const revision = ref('');
const busy = ref(true);
const error = ref('');
const heading = ref<HTMLElement | null>(null);
let controller: AbortController | undefined;
let sequence = 0;
let retry: (() => void) | undefined;
const labels: Record<string,string> = { close:'Cierre de importación', configuration:'Configuración y revaluación', update:'Actualización del lote', product_create:'Producto agregado', product_update:'Producto actualizado', product_delete:'Producto eliminado', expense_create:'Gasto agregado', expense_update:'Gasto actualizado', expense_delete:'Gasto eliminado', document_upload:'Documento adjunto', document_trash:'Documento a papelera', document_restore:'Documento restaurado', company_assignment:'Empresa asignada', cost_reconcile:'Conciliación de costos', scenario_apply:'Escenario aplicado', price_target:'Precios por objetivo', price_update:'Precio de venta actualizado', products_import:'Productos importados por lote' };
const algorithms: Record<string,string> = { 'largest-remainder-clp-v1':'Reparto en pesos enteros' };
const date = (value: string) => new Date(value).toLocaleString('es-CL');
const cancel = () => { sequence++; controller?.abort(); busy.value = false; };
watch(() => [props.trip.id, props.trip.revision], () => load({ page:1, reason:reason.value || undefined, revision:revision.value || undefined }, false));
onMounted(() => load({ page:1 }, false));
onBeforeUnmount(cancel);
async function load(params: { page: number; anchor?: number | null; reason?: string; revision?: string }, focus = true) {
    cancel(); const current = sequence; controller = new AbortController(); busy.value = true; error.value = '';
    const captured = { ...params };
    retry = () => { load(captured, focus); };
    let loaded = false;
    try {
        const response = await axios.get<CostRunHistory>(route('trips.cost-runs.index', props.trip.id), { params: captured, signal:controller.signal, headers:{ Accept:'application/json' } });
        if (current === sequence) { listing.value = response.data; loaded = true; }
    } catch (failure) {
        if (current === sequence && !axios.isCancel(failure)) error.value = axios.isAxiosError<{ errors?: Record<string,string[]> }>(failure) && failure.response?.status === 422 ? Object.values(failure.response.data.errors || {}).flat().join(' ') : 'No se pudieron cargar las revisiones. Vuelve a intentarlo.';
    } finally {
        if (current === sequence) { busy.value = false; if (loaded && focus) { await nextTick(); heading.value?.focus(); } }
    }
}
const filter = () => load({ page:1, reason:reason.value || undefined, revision:revision.value || undefined });
const clear = () => { reason.value=''; revision.value=''; filter(); };
const page = (number: number) => load({ page:number, anchor:listing.value.anchor, reason:listing.value.reason || undefined, revision:listing.value.revision?.toString() });
</script>
<template>
    <section class="f-panel" aria-labelledby="cost-history-heading" :aria-busy="busy">
        <div class="f-panel-heading"><h2 ref="heading" id="cost-history-heading" tabindex="-1">Revisiones conservadas</h2><span class="f-hint">{{ listing.total }} {{ listing.total === 1 ? 'ejecución' : 'ejecuciones' }} · reportes originales</span></div>
        <form class="f-panel-body f-filters" @submit.prevent="filter"><div class="f-field"><label for="cost-history-reason">Mostrar</label><select class="f-input" id="cost-history-reason" v-model="reason" :disabled="busy"><option value="">Todos los cálculos</option><option value="close">Solo cierres</option></select></div><div class="f-field"><label for="cost-history-revision">Revisión exacta</label><input class="f-input" id="cost-history-revision" type="number" min="1" max="2147483647" step="1" placeholder="Todas" v-model="revision" :disabled="busy" /></div><button class="f-button f-button-primary" :disabled="busy">{{ busy ? 'Cargando…' : 'Buscar revisiones' }}</button><button v-if="reason || revision" type="button" class="f-button" :disabled="busy" @click="clear">Limpiar búsqueda</button></form>
        <div class="f-panel-body" style="padding-top:0"><div v-if="error" class="f-notice f-notice-error" role="alert">{{ error }} <button class="f-button" :disabled="busy" @click="retry?.()">Reintentar</button></div><p v-if="busy" role="status" class="f-hint">Cargando revisiones conservadas…</p>
            <article v-for="run in listing.data" :key="run.id" class="f-breakdown-row cost-history-row"><div><strong>Revisión {{ run.revision }} · {{ labels[run.reason] || run.reason }}</strong><p class="f-hint">{{ date(run.created_at) }} · {{ algorithms[run.algorithm] || run.algorithm }}</p></div><div class="f-actions"><a :href="`${route('trips.export.pdf',trip.id)}?run=${run.id}`" class="f-button" :aria-label="`PDF de la revisión ${run.revision}`">PDF de esta revisión</a><a :href="`${route('trips.export.excel',trip.id)}?run=${run.id}`" class="f-button" :aria-label="`Excel de la revisión ${run.revision}`">Excel de esta revisión</a></div></article>
            <p v-if="!listing.data.length && !busy" class="f-hint">{{ listing.reason || listing.revision ? 'No hay ejecuciones para esta búsqueda.' : 'Aún no hay una ejecución registrada. Los datos heredados conservan su procedencia sin verificar.' }}</p>
            <p class="f-hint" style="margin-top:14px">La lista conserva su alcance al cambiar de página, aunque se creen nuevos cálculos. Buscar de nuevo actualiza el historial. Papelera y reapertura registran cambios de estado sin generar un cálculo; los cierres anteriores conservan sus reportes.</p>
        </div>
        <div v-if="listing.total" class="f-pagination"><span>{{ listing.from }}–{{ listing.to }} de {{ listing.total }} ejecuciones</span><nav class="f-pagination-links" aria-label="Páginas de revisiones conservadas"><button class="f-button" :disabled="busy || listing.current_page <= 1" @click="page(listing.current_page-1)">Más recientes</button><span class="f-hint" style="padding:8px">{{ listing.current_page }} / {{ listing.last_page }}</span><button class="f-button" :disabled="busy || listing.current_page >= listing.last_page" @click="page(listing.current_page+1)">Más antiguas</button></nav></div>
    </section>
</template>
<style scoped>
.cost-history-row{flex-wrap:wrap;align-items:center}.cost-history-row>div:first-child{min-width:0;overflow-wrap:anywhere}.f-field{min-width:140px}.f-panel-heading h2:focus{outline:3px solid var(--f-brand);outline-offset:4px}@media(max-width:760px){.f-field{flex:1}.f-pagination-links{flex-wrap:wrap}}
</style>
