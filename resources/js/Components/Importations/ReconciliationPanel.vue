<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import axios from 'axios';
import { router } from '@inertiajs/vue3';
import { useRoute } from '@/Composables/useRoute';
import { formatMoney } from '@/Utils/format';
import { requestErrors, validationErrors } from '@/Utils/validation';
import type { ImportDetail, Pagination } from '@/Types/financial';
import type { CostComparison, ComparisonSummary, DifferenceClassification, ReconciliationApply } from '@/Types/reconciliation';
const props = defineProps<{ trip: ImportDetail }>();
const route = useRoute();
const comparison = ref<CostComparison | null>(null);
const listing = ref<Pagination<ComparisonSummary> | null>(null);
const acknowledged = ref<string[]>([]);
const busy = ref(false);
const applying = ref(false);
const processing = computed(()=>busy.value || applying.value);
const error = ref('');
let controller: AbortController | undefined;
let sequence = 0;
const classes: Record<DifferenceClassification,string> = { conversion:'Conversión · revisar origen', clp_rounding:'Redondeo al peso CLP', allocation:'Reparto de gastos', line_total:'Total de la línea', stored_inconsistency:'Total guardado inconsistente', unit_precision:'Costo unitario y precisión' };
const fields: Record<string,string> = { subtotal_clp:'Compra', gastos_prorrateados_clp:'Gastos asignados', costo_final_total_clp:'Costo final total', costo_final_unitario_clp:'Costo unitario', monto_clp:'Gasto convertido' };
const totals = { purchase:'Compra', expenses:'Gastos', assigned:'Gastos asignados', unassigned:'Sin asignar', total:'Costo del lote', allocated_total:'Total de líneas' } as const;
const available = computed(() => !!comparison.value?.id && !comparison.value.errors.length && comparison.value.differences.total > 0 && !comparison.value.applied_revision && comparison.value.revision === props.trip.revision && props.trip.estado === 'activo');
const ready = computed(() => available.value && acknowledged.value.length === comparison.value?.differences.total);
const cancel = () => { sequence++; controller?.abort(); busy.value = false; };
const explain = (failure: unknown) => Object.values(requestErrors(failure,'comparison','No se pudo completar la operación. Vuelve a intentarlo.')).flat().join(' ');
const request = async (action: (signal: AbortSignal) => Promise<void>) => {
    cancel(); const current = sequence; controller = new AbortController(); busy.value = true; error.value = '';
    try { await action(controller.signal); }
    catch(failure) { if (current === sequence && !axios.isCancel(failure)) error.value = explain(failure); }
    finally { if (current === sequence) busy.value = false; }
};
const accept = (data: CostComparison) => {
    if (comparison.value?.fingerprint !== data.fingerprint || comparison.value?.id !== data.id) acknowledged.value = [];
    comparison.value = data;
};
const loadList = (page = 1) => request(async signal => {
    const current = sequence;
    const response = await axios.get<Pagination<ComparisonSummary>>(route('trips.cost-comparisons.index',props.trip.id),{ params:{page},signal });
    if (current === sequence) listing.value = response.data;
});
const load = (id?: number,page = 1) => request(async signal => {
    const current = sequence;
    const url = id ? route('trips.cost-comparisons.show',[props.trip.id,id]) : route('trips.cost-comparisons.preview',props.trip.id);
    const response = await axios.get<CostComparison>(url,{ params:{page},signal });
    if (current === sequence) accept(response.data);
});
const save = () => request(async signal => {
    if (!comparison.value) return;
    const current = sequence;
    const response = await axios.post<CostComparison>(route('trips.cost-comparisons.store',props.trip.id),{ expected_revision:comparison.value.revision, fingerprint:comparison.value.fingerprint },{signal});
    if (current === sequence) {
        accept(response.data);
        router.reload({ only:['legacyBaseline'] });
        const savedList = await axios.get<Pagination<ComparisonSummary>>(route('trips.cost-comparisons.index',props.trip.id),{signal});
        if (current === sequence) listing.value = savedList.data;
    }
});
const apply = () => {
    if (!ready.value || !comparison.value?.id) return;
    applying.value = true; error.value = '';
    const input: ReconciliationApply = { expected_revision:comparison.value.revision, acknowledged_keys:[...acknowledged.value] };
    router.post(route('trips.cost-comparisons.apply',[props.trip.id,comparison.value.id]),{...input},{ preserveScroll:true,
        onError:errors=>{ error.value = Object.values(validationErrors(errors)).flat().join(' '); },
        onSuccess:()=>{ comparison.value = null; acknowledged.value = []; loadList(); },onFinish:()=>{ applying.value = false; } });
};
watch(()=>props.trip.revision,()=>{ cancel(); comparison.value = null; acknowledged.value = []; loadList(); });
onMounted(()=>loadList());
onBeforeUnmount(cancel);
</script>
<template>
    <section class="f-panel" :aria-busy="processing">
        <div class="f-panel-heading"><h2>Conciliación de costos</h2><button class="f-button" :disabled="processing" @click="load()">Comparar costos guardados</button></div>
        <div class="f-panel-body">
            <p class="f-hint">Compara los importes guardados con el motor actual. Conserva la comparación y revisa cada diferencia antes de aplicar una nueva revisión.</p>
            <p v-if="error" class="f-notice f-notice-error" role="alert">{{ error }}</p>
            <p v-if="processing" role="status" class="f-hint">Procesando comparación…</p>
            <template v-if="comparison">
                <p class="f-hint" style="margin-top:16px">Revisión {{ comparison.revision }} · {{ comparison.id ? `Comparación conservada #${comparison.id}` : 'Vista previa sin guardar' }} · {{ comparison.differences.total }} diferencias</p>
                <p v-if="comparison.applied_revision" class="f-notice" role="status">Aplicada en la revisión {{ comparison.applied_revision }}. Los valores anteriores se conservan.</p>
                <p v-if="comparison.revision !== trip.revision" class="f-notice">Comparación histórica. Para conciliar la revisión vigente, vuelve a comparar los costos guardados.</p>
                <div v-for="message in comparison.errors" :key="message" class="f-notice f-notice-error" role="alert">{{ message }}</div>
                <div v-if="comparison.before && comparison.after" class="reconciliation-summary">
                    <div v-for="(label,key) in totals" :key="key" class="reconciliation-total"><strong>{{ label }} · CLP</strong><div><span class="f-hint">Guardado</span><p>${{ formatMoney(comparison.before[key],2) }}</p></div><div><span class="f-hint">Motor actual</span><p>${{ formatMoney(comparison.after[key],2) }}</p></div></div>
                </div>
                <p v-if="comparison.warnings_count" class="f-notice">{{ comparison.warnings_count }} registros conservan una procedencia de tasa sin verificar. Este cálculo usa los datos registrados; no certifica la tasa histórica ni completa fuentes o fechas ausentes.</p>
                <details v-if="comparison.warnings_count"><summary class="f-hint">Ver registros sin procedencia verificada · primeros {{ comparison.warnings.length }}</summary><p v-for="warning in comparison.warnings" :key="`${warning.kind}-${warning.id}`" class="f-hint">{{ warning.kind === 'products' ? 'Producto' : 'Gasto' }} #{{ warning.id }} · {{ warning.message }}</p></details>
                <p v-if="!comparison.differences.total && !comparison.errors.length" class="f-notice">Los costos guardados coinciden con el motor actual. No requieren conciliación.</p>
                <article v-for="row in comparison.differences.data" :key="row.key" class="reconciliation-difference">
                    <strong>{{ row.name }}</strong><p class="f-hint">{{ row.kind === 'products' ? 'Producto' : 'Gasto' }} #{{ row.id }} · {{ fields[row.field] || row.field }} · {{ classes[row.classification] }}</p>
                    <div class="reconciliation-values"><div><span class="f-hint">Guardado · CLP</span><p>${{ formatMoney(row.before,row.field === 'costo_final_unitario_clp' ? 8 : 2) }}</p></div><div><span class="f-hint">Motor actual · CLP</span><p>${{ formatMoney(row.after,row.field === 'costo_final_unitario_clp' ? 8 : 2) }}</p></div><div><span class="f-hint">Diferencia · CLP</span><p>${{ formatMoney(row.delta,row.field === 'costo_final_unitario_clp' ? 8 : 2) }}</p></div></div>
                    <label v-if="available" class="reconciliation-check"><input type="checkbox" v-model="acknowledged" :value="row.key" :disabled="processing" /> He revisado {{ fields[row.field] || row.field }} de {{ row.name }}</label>
                </article>
                <div v-if="comparison.differences.last_page > 1" class="f-pagination"><span>Diferencias {{ comparison.differences.current_page }} / {{ comparison.differences.last_page }}</span><div class="f-actions"><button class="f-button" :disabled="processing || comparison.differences.current_page <= 1" @click="load(comparison.id,comparison.differences.current_page-1)">Anteriores</button><button class="f-button" :disabled="processing || comparison.differences.current_page >= comparison.differences.last_page" @click="load(comparison.id,comparison.differences.current_page+1)">Siguientes</button></div></div>
                <div class="f-actions" style="margin-top:16px"><button v-if="!comparison.id" class="f-button" :disabled="processing || comparison.revision !== trip.revision" @click="save">Conservar comparación</button><button v-if="available" class="f-button f-button-primary" :disabled="processing || !ready" @click="apply">Aplicar conciliación · {{ acknowledged.length }}/{{ comparison.differences.total }} revisadas</button></div>
                <p v-if="trip.estado !== 'activo'" class="f-hint" style="margin-top:12px">La importación cerrada conserva sus importes. Reabrir permite revisar una conciliación nueva y mantiene la copia del cierre original.</p>
            </template>
            <div v-if="listing?.data.length" style="margin-top:20px"><h3>Comparaciones conservadas</h3><div v-for="item in listing.data" :key="item.id" class="f-breakdown-row" style="flex-wrap:wrap"><span>Revisión {{ item.base_revision }} · #{{ item.id }} <span class="f-hint">{{ item.applied_revision ? `Aplicada en ${item.applied_revision}` : 'Sin aplicar' }}</span></span><button class="f-button" :disabled="processing" @click="load(item.id)">Revisar comparación #{{ item.id }}</button></div><div v-if="listing.last_page > 1" class="f-pagination"><span>{{ listing.current_page }} / {{ listing.last_page }}</span><div class="f-actions"><button class="f-button" :disabled="processing || listing.current_page <= 1" @click="loadList(listing.current_page-1)">Comparaciones anteriores</button><button class="f-button" :disabled="processing || listing.current_page >= listing.last_page" @click="loadList(listing.current_page+1)">Comparaciones siguientes</button></div></div></div>
        </div>
    </section>
</template>
<style scoped>
.reconciliation-summary{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px;margin:16px 0}.reconciliation-total{border:1px solid var(--f-line);border-radius:8px;padding:12px;display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:8px;font-size:12px;overflow-wrap:anywhere}.reconciliation-total>strong{grid-column:1/-1}.reconciliation-difference{border-bottom:1px solid var(--f-line);padding:16px 0;overflow-wrap:anywhere}.reconciliation-values{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px;margin-top:12px;font-variant-numeric:tabular-nums;font-size:13px}.reconciliation-check{display:flex;align-items:flex-start;gap:10px;margin-top:14px;font-size:12px;cursor:pointer}.reconciliation-check input{margin-top:2px;flex-shrink:0;accent-color:var(--f-accent)}@media(max-width:600px){.reconciliation-summary{grid-template-columns:1fr}.reconciliation-values{grid-template-columns:1fr}}
</style>
