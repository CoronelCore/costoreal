<script setup lang="ts">
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import axios from 'axios';
import Modal from '@/Components/UI/Modal.vue';
import { useRoute } from '@/Composables/useRoute';
import { formatMoney, formatDateOnly, formatRate } from '@/Utils/format';
import type { AllocationMethod, CostBreakdown, CostRunSummary, ProductData } from '@/Types/financial';

const props = defineProps<{ tripId: number; currentRevision: number; product: ProductData | null; run: CostRunSummary | null }>();
const emit = defineEmits<{ close: []; closed: [] }>();
const route = useRoute();
const result = ref<CostBreakdown | null>(null);
const displayedProduct = ref<ProductData | null>(null);
const displayedRevision = ref(props.currentRevision);
const displayedRun = ref<CostRunSummary | null>(null);
const busy = ref(false);
const error = ref('');
let controller: AbortController | undefined;
let sequence = 0;
const cancel = () => { sequence++; controller?.abort(); busy.value = false; };
const methods: Record<AllocationMethod, string> = {value:'Valor de compra',units:'Unidades',weight:'Peso total',volume:'Volumen total',direct:'Asignación directa'};
const basisUnit: Record<AllocationMethod, string> = {value:'CLP',units:'unidades',weight:'kg',volume:'m³',direct:'indicador de destino'};
const line = computed(() => result.value?.line);
const source = computed(() => line.value?.rate_source === 'legacy-unverified' ? 'Procedencia pendiente de verificar' : line.value?.rate_source === 'trip-reference' ? 'Referencia de la importación' : line.value?.rate_source);
const load = async (page = 1) => {
    cancel();
    const product = props.product;
    const run = props.run;
    if (!product || !run) return;
    const current = sequence;
    controller = new AbortController(); busy.value = true; error.value = '';
    try {
        const response = await axios.get<CostBreakdown>(route('trips.cost-breakdown.show',[props.tripId,run.id,product.id]),{params:{page},signal:controller.signal,headers:{Accept:'application/json'}});
        if (current === sequence) result.value = response.data;
    } catch (failure) {
        if (current === sequence && !axios.isCancel(failure)) {
            const status = axios.isAxiosError(failure) ? failure.response?.status : null;
            error.value = status === 409 ? 'El detalle de esta revisión antigua está pendiente de preparar. Sus costos originales se conservan.' :
                status === 404 ? 'Este producto no tiene detalle disponible en la revisión seleccionada. Se conservan los valores guardados de la hoja.' :
                    'No se pudo consultar el detalle. Conservamos los valores guardados; puedes reintentar.';
        }
    } finally { if (current === sequence) busy.value = false; }
};
watch(()=>[props.product?.id,props.run?.id,props.currentRevision],()=>{
    cancel();
    if (!props.product) return;
    displayedProduct.value = props.product;
    displayedRevision.value = props.currentRevision;
    displayedRun.value = props.run;
    result.value = null; error.value = ''; void load();
},{immediate:true});
const finishClosed = () => {
    if (props.product) return;
    displayedProduct.value = null; displayedRun.value = null; result.value = null; error.value = '';
    emit('closed');
};
onBeforeUnmount(cancel);
const close = () => { cancel(); emit('close'); };
</script>

<template>
    <Modal :show="!!product" :title="`Costo de ${line?.name ?? displayedProduct?.nombre ?? 'producto'}`" @close="close" @closed="finishClosed">
        <template v-if="displayedProduct">
            <p class="f-hint" style="margin-bottom:16px">{{ result ? `Cálculo conservado · revisión ${result.run.revision}` : `Valores guardados de la hoja · revisión ${displayedRevision}` }}</p>
            <p v-if="busy" role="status" class="f-hint">Consultando el desglose…</p>
            <div v-if="error" class="f-notice f-notice-error" role="alert" style="margin:12px 0">{{ error }}<button class="f-button" style="margin-top:8px" @click="load(result?.allocations.current_page ?? 1)" :disabled="busy || !product">Reintentar detalle</button></div>
            <div class="f-breakdown-row"><span>Compra convertida</span><strong>${{ formatMoney(line?.purchase ?? displayedProduct.subtotal_clp) }}</strong></div>
            <div class="f-breakdown-row"><span>Gastos asignados</span><strong>${{ formatMoney(line?.assigned ?? displayedProduct.gastos_prorrateados_clp) }}</strong></div>
            <div class="f-breakdown-row"><span>Total de {{ line?.quantity ?? displayedProduct.cantidad }} {{ (line?.quantity ?? displayedProduct.cantidad) === 1 ? 'unidad' : 'unidades' }}</span><strong>${{ formatMoney(line?.total ?? displayedProduct.costo_final_total_clp) }}</strong></div>
            <div class="f-breakdown-row"><span>Costo por unidad · CLP</span><strong>${{ formatMoney(line?.unit_cost ?? displayedProduct.costo_final_unitario_clp,2) }}</strong></div>
            <template v-if="line && result">
                <p class="f-hint" style="margin:12px 0"><template v-if="line.currency === 'CLP'">Moneda original CLP · conversión 1:1</template><template v-else>Tasa {{ line.rate_mode === 'paid' ? 'histórica de pago' : 'estimada' }}: {{ formatRate(line.rate) }} CLP / {{ line.currency }} · fuente: {{ source }}<template v-if="line.rate_date"> · {{ formatDateOnly(line.rate_date) }}</template></template></p>
                <p v-if="result.run.revision !== result.current_revision" class="f-notice">Este cálculo corresponde a la revisión {{ result.run.revision }}; la importación está en la {{ result.current_revision }}. Se muestran los valores conservados de ese cálculo.</p>
                <h3 style="margin:20px 0 8px;font-weight:650">Gastos que componen el costo</h3>
                <p class="f-hint">{{ result.allocations.total }} {{ result.allocations.total === 1 ? 'reparto' : 'repartos' }} · importes en CLP enteros</p>
                <article v-for="allocation in result.allocations.data" :key="allocation.expense_id" class="cost-allocation">
                    <div class="f-breakdown-row"><span><strong>{{ allocation.name }}</strong><small class="f-hint" style="display:block">{{ methods[allocation.method] }}</small></span><strong>${{ formatMoney(allocation.amount) }}</strong></div>
                    <details v-if="allocation.trace" class="cost-trace"><summary>Cómo se asignó este gasto<template v-if="allocation.trace.residual_award"> · +1 CLP residual</template></summary>
                        <dl><div><dt>Base de este producto</dt><dd>{{ allocation.trace.basis }} {{ basisUnit[allocation.method] }}</dd></div><div><dt>Base del lote</dt><dd>{{ allocation.trace.basis_total }} {{ basisUnit[allocation.method] }}</dd></div><div><dt>Gasto a distribuir</dt><dd>${{ formatMoney(allocation.trace.expense_amount) }}</dd></div><div><dt>Parte entera</dt><dd>${{ formatMoney(allocation.trace.floor) }}</dd></div><div><dt>Fracción restante exacta</dt><dd>{{ allocation.trace.remainder_numerator }} / {{ allocation.trace.remainder_denominator }} CLP</dd></div><div><dt>Ajuste residual</dt><dd>+{{ allocation.trace.residual_award }} CLP</dd></div><div><dt>Orden de prioridad</dt><dd>{{ allocation.trace.priority }} · {{ allocation.trace.residual_pool }} CLP por distribuir</dd></div></dl>
                        <p class="f-hint">Se entrega un peso a las mayores fracciones restantes. Los empates se resuelven por el identificador del producto en orden ascendente.</p>
                        <p style="margin-top:8px;font-weight:600">${{ formatMoney(allocation.trace.floor) }} + {{ allocation.trace.residual_award }} CLP = ${{ formatMoney(allocation.amount) }}</p>
                    </details>
                    <p v-else class="f-hint" style="margin-top:8px">El detalle de redondeo no fue registrado en esta revisión. El importe asignado es el original.</p>
                </article>
                <p v-if="!result.allocations.total" class="f-hint" style="margin-top:12px">Esta línea no tiene repartos de gastos registrados.</p>
                <div v-if="result.allocations.last_page > 1" class="f-pagination"><span>{{ result.allocations.current_page }} / {{ result.allocations.last_page }}</span><div class="f-actions"><button class="f-button" @click="load(result.allocations.current_page-1)" :disabled="busy || !product || result.allocations.current_page <= 1">Anteriores</button><button class="f-button" @click="load(result.allocations.current_page+1)" :disabled="busy || !product || result.allocations.current_page >= result.allocations.last_page">Siguientes</button></div></div>
                <p v-if="result.totals.unassigned !== '0'" class="f-notice" style="margin-top:12px">El lote conserva ${{ formatMoney(result.totals.unassigned) }} sin asignar. Ese importe está incluido en su costo total y no en el costo de este producto.</p>
            </template>
            <p v-if="!displayedRun" class="f-notice" style="margin-top:16px">Datos heredados: este lote aún no tiene una ejecución de costo verificable. Los valores guardados de la hoja se conservan.</p>
        </template>
    </Modal>
</template>

<style scoped>
.cost-allocation{padding:12px 0;border-bottom:1px solid var(--f-line);overflow-wrap:anywhere}.cost-allocation:last-child{border-bottom:0}.cost-trace{margin-top:10px;padding:12px;background:var(--f-bg);border:1px solid var(--f-line);border-radius:8px;font-size:12px}.cost-trace summary{cursor:pointer;font-weight:600;min-height:28px}.cost-trace dl{margin:12px 0}.cost-trace dl div{display:grid;grid-template-columns:minmax(0,1fr) minmax(0,1fr);gap:12px;padding:6px 0}.cost-trace dd{text-align:right;font-variant-numeric:tabular-nums}.f-breakdown-row{flex-wrap:wrap;gap:8px}
</style>
