<script setup lang="ts">
import { onBeforeUnmount, ref, watch } from 'vue';
import axios from 'axios';
import ReconciliationPanel from './ReconciliationPanel.vue';
import type { LegacyBaseline } from '@/Types/reconciliation';
import { formatMoney, formatDateOnly } from '@/Utils/format';
import { useRoute } from '@/Composables/useRoute';
import type { ImportDetail, Pagination, AuditEventSummary, CostRunSummary } from '@/Types/financial';
import type { AuditComparison, AuditValue } from '@/Types/advanced';
const props = defineProps<{ trip: ImportDetail; history: Pagination<AuditEventSummary>; runs: CostRunSummary[]; baseline: LegacyBaseline | null }>();
const route = useRoute();
const listing = ref(props.history);
const expanded = ref<number | null>(null);
const comparison = ref<AuditComparison | null>(null);
const busy = ref(false);
const error = ref('');
let controller: AbortController | undefined;
let sequence = 0;
const labels: Record<string,string> = { update:'Actualización del lote', products_import:'Productos importados por lote', product_create:'Producto agregado', product_update:'Producto actualizado', product_delete:'Producto eliminado', expense_create:'Gasto agregado', expense_update:'Gasto actualizado', expense_delete:'Gasto eliminado', scenario_apply:'Escenario aplicado', price_target:'Precios por objetivo', price_update:'Precio de venta actualizado', document_upload:'Documento adjunto', document_trash:'Documento a papelera', document_restore:'Documento restaurado', company_assignment:'Empresa asignada', cost_reconcile:'Conciliación de costos', configuration:'Configuración y revaluación', close:'Cierre de importación', reopen:'Reapertura de importación', trip_trash:'Importación a papelera', trip_restore:'Importación restaurada' };
const fields: Record<string,string> = { nombre:'Nombre', descripcion:'Descripción', company_id:'Empresa · identificador', estado:'Estado', fecha_inicio:'Fecha de inicio', fecha_fin:'Fecha de fin', moneda_trabajo:'Moneda de trabajo', tipo_cambio:'Tasa de referencia', costo_total:'Costo del lote · CLP', deleted_at:'Fecha en papelera', cantidad:'Cantidad', moneda:'Moneda original', costo_unitario:'Costo unitario original', rate_mode:'Tipo de conversión', applied_rate:'Tasa aplicada', rate_source:'Fuente de la tasa', rate_date:'Fecha de la tasa', weight:'Peso total · kg', volume:'Volumen total · m³', subtotal_clp:'Compra · CLP', gastos_prorrateados_clp:'Gastos asignados · CLP', costo_final_total_clp:'Costo final total · CLP', costo_final_unitario_clp:'Costo final unitario · CLP', precio_venta:'Precio unitario de venta · CLP', document_id:'Documento · identificador', concepto:'Concepto', expense_category_id:'Categoría · identificador', monto:'Monto original', monto_clp:'Monto convertido · CLP', allocation_method:'Regla de reparto', target_product_id:'Producto destino · identificador', filename:'Archivo', mime:'Tipo de archivo', size:'Tamaño · bytes', kind:'Uso del documento' };
const states: Record<string,string> = { activo:'En curso',finalizado:'Cerrada',archivado:'Archivada',estimated:'Estimado',paid:'Pagado',value:'Valor de compra',units:'Unidades',weight:'Peso',volume:'Volumen',direct:'Producto directo' };
const moneyFields = new Set(['costo_total','subtotal_clp','gastos_prorrateados_clp','costo_final_total_clp','costo_final_unitario_clp','precio_venta','monto_clp']);
const stateLabel = (state: string | null) => state === null ? 'Sin dato' : states[state] || state;
const value = (field: string, data: AuditValue | undefined) => data === null || data === undefined ? 'Sin dato' : moneyFields.has(field) ? '$'+formatMoney(String(data),['costo_final_unitario_clp','precio_venta'].includes(field) ? 2 : 0) : field === 'deleted_at' ? date(String(data)) : ['fecha_inicio','fecha_fin','rate_date'].includes(field) ? formatDateOnly(String(data)) : (['estado','rate_mode','allocation_method'].includes(field) ? stateLabel(String(data)) : String(data));
const date = (value: string) => new Date(value).toLocaleString('es-CL');
const cancel = () => { sequence++; controller?.abort(); busy.value = false; };
watch(()=>props.history,data=>{ cancel(); listing.value = data; expanded.value = null; comparison.value = null; });
onBeforeUnmount(cancel);
const load = async (event: AuditEventSummary,page = 1) => {
    cancel(); const current = sequence; controller = new AbortController(); busy.value = true; error.value = '';
    try {
        const response = await axios.get<AuditComparison>(route('trips.audit.show',[props.trip.id,event.id]),{params:{page},signal:controller.signal,headers:{Accept:'application/json'}});
        if (current === sequence) comparison.value = response.data;
    } catch(failure) { if (current === sequence && !axios.isCancel(failure)) error.value = 'No se pudo abrir la comparación. Vuelve a intentarlo.'; }
    finally { if (current === sequence) busy.value = false; }
};
const toggle = (event: AuditEventSummary) => {
    if (expanded.value === event.id) { cancel(); expanded.value = null; comparison.value = null; return; }
    expanded.value = event.id; comparison.value = null; load(event);
};
const page = async (number: number) => {
    cancel(); const current = sequence; controller = new AbortController(); busy.value = true; error.value = '';
    try {
        const response = await axios.get<Pagination<AuditEventSummary>>(route('trips.audit.index',props.trip.id),{params:{page:number},signal:controller.signal,headers:{Accept:'application/json'}});
        if (current === sequence) { listing.value = response.data; expanded.value = null; comparison.value = null; }
    } catch(failure) { if (current === sequence && !axios.isCancel(failure)) error.value = 'No se pudo cambiar de página. Vuelve a intentarlo.'; }
    finally { if (current === sequence) busy.value = false; }
};
</script>
<template>
    <ReconciliationPanel :trip="trip" />
    <section v-if="baseline" class="f-panel"><div class="f-panel-heading"><h2>Copia heredada conservada</h2><span class="f-hint">Revisión {{ baseline.revision }}</span></div><div class="f-panel-body"><p class="f-hint">Importes originales anteriores a su primer cambio o conciliación. El algoritmo y la procedencia histórica de las tasas permanecen sin verificar.</p><div class="f-actions" style="margin-top:12px"><a :href="`${route('trips.export.pdf',trip.id)}?baseline=${baseline.id}`" class="f-button">PDF de los importes originales</a><a :href="`${route('trips.export.excel',trip.id)}?baseline=${baseline.id}`" class="f-button">Excel de los importes originales</a></div></div></section>
    <section class="f-panel"><div class="f-panel-heading"><h2>Revisiones conservadas</h2><span class="f-hint">Últimas 50 ejecuciones de costeo</span></div><div class="f-panel-body"><div v-for="run in runs" :key="run.id" class="f-breakdown-row" style="flex-wrap:wrap;align-items:center"><div><strong>Revisión {{ run.revision }} · {{ labels[run.reason] || run.reason }}</strong><p class="f-hint">{{ date(run.created_at) }} · {{ run.algorithm }}</p></div><div class="f-actions"><a :href="`${route('trips.export.pdf',trip.id)}?run=${run.id}`" class="f-button">PDF de esta revisión</a><a :href="`${route('trips.export.excel',trip.id)}?run=${run.id}`" class="f-button">Excel de esta revisión</a></div></div><p class="f-hint" style="margin-top:12px">La papelera y la restauración registran cambios de estado sin generar un nuevo cálculo. Los cierres anteriores conservan sus reportes.</p><p v-if="!runs.length" class="f-hint">Aún no hay una ejecución registrada. Los datos heredados conservan su procedencia sin verificar.</p></div></section>
    <section class="f-panel"><div class="f-panel-heading"><h2>Registro de cambios</h2><span class="f-hint">{{ listing?.total || 0 }} eventos · comparación bajo demanda</span></div><div class="f-panel-body">
        <div v-if="error" class="f-notice f-notice-error" role="alert">{{ error }}</div>
        <article v-for="event in listing?.data || []" :key="event.id" class="audit-event"><button class="f-breakdown-row" style="width:100%;text-align:left;border:0;padding:0;flex-wrap:wrap" :aria-expanded="expanded === event.id" :aria-controls="`audit-${event.id}`" @click="toggle(event)"><span><strong>{{ labels[event.action] || event.action }}</strong><span class="f-table-subtitle">{{ event.actor?.name || 'Proceso del sistema' }} · {{ date(event.created_at) }}</span></span><span class="f-hint">Revisión {{ event.revision }} {{ expanded === event.id ? '−' : '+' }}</span></button>
            <div v-if="expanded === event.id" :id="`audit-${event.id}`" class="f-draft" style="margin-top:12px" :aria-busy="busy">
                <template v-if="event.summary"><div class="f-breakdown-row"><span>Estado</span><strong>{{ stateLabel(event.summary.before.state) }} → {{ stateLabel(event.summary.after.state) }}</strong></div><div class="f-breakdown-row"><span>Costo del lote · CLP</span><strong>{{ value('costo_total',event.summary.before.cost) }} → {{ value('costo_total',event.summary.after.cost) }}</strong></div><div class="f-breakdown-row"><span>Productos · gastos · documentos</span><strong>{{ event.summary.before.products }} · {{ event.summary.before.expenses }} · {{ event.summary.before.documents }} → {{ event.summary.after.products }} · {{ event.summary.after.expenses }} · {{ event.summary.after.documents }}</strong></div></template>
                <p v-else class="f-hint">Resumen heredado pendiente de completar. Puedes consultar las diferencias originales.</p>
                <p v-if="busy" role="status" class="f-hint" style="margin-top:16px">Cargando diferencias…</p>
                <template v-if="comparison?.id === event.id"><h3 style="margin-top:20px;font-weight:600">Diferencias por registro · {{ comparison.total }}</h3><p class="f-hint">Valores originales conservados, antes y después de este cambio. Los identificadores permiten reconocer los registros históricos.</p><article v-for="row in comparison.data" :key="`${row.kind}-${row.id}`" class="audit-diff"><strong>{{ row.name }}</strong><p class="f-hint">{{ ({trip:'Importación',products:'Producto',expenses:'Gasto',documents:'Documento'})[row.kind] }} #{{ row.id }} · {{ ({added:'Agregado',removed:'Retirado',updated:'Actualizado'})[row.change] }}</p><div v-for="field in row.fields" :key="field.field" class="audit-field"><strong>{{ fields[field.field] || field.field }}</strong><div class="audit-values"><div><span class="f-hint">Antes</span><p>{{ value(field.field,field.before) }}</p></div><div><span class="f-hint">Después</span><p>{{ value(field.field,field.after) }}</p></div></div></div></article><p v-if="!comparison.total" class="f-hint" style="margin-top:12px">No hay diferencias en los campos del expediente.</p><div v-if="comparison.last_page > 1" class="f-pagination"><span>{{ comparison.current_page }} / {{ comparison.last_page }}</span><div class="f-actions"><button class="f-button" @click="load(event,comparison.current_page-1)" :disabled="busy || comparison.current_page <= 1">Diferencias anteriores</button><button class="f-button" @click="load(event,comparison.current_page+1)" :disabled="busy || comparison.current_page >= comparison.last_page">Más diferencias</button></div></div></template>
                <button v-if="error && !busy" class="f-button" style="margin-top:12px" @click="load(event)">Reintentar comparación</button>
            </div>
        </article>
        <p v-if="!listing?.data.length" class="f-hint">Los nuevos cambios se registrarán aquí con su autor.</p><div v-if="listing?.last_page > 1" class="f-pagination"><span>{{ listing.current_page }} / {{ listing.last_page }}</span><div class="f-actions"><button class="f-button" @click="page(listing.current_page-1)" :disabled="busy || listing.current_page <= 1">Eventos anteriores</button><button class="f-button" @click="page(listing.current_page+1)" :disabled="busy || listing.current_page >= listing.last_page">Eventos siguientes</button></div></div>
    </div></section>
</template>
<style scoped>
.audit-event{border-bottom:1px solid var(--f-line);padding:14px 0}.audit-event:last-child{border:0}.audit-diff{border-top:1px solid var(--f-line);padding:16px 0;margin-top:12px;overflow-wrap:anywhere}.audit-field{border-top:1px solid var(--f-line);padding:10px 0;margin-top:10px;font-size:12px}.audit-values{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:16px;margin-top:8px;overflow-wrap:anywhere}.audit-values p{margin-top:4px}.audit-event .f-breakdown-row{flex-wrap:wrap}
</style>
