<script setup>
import { computed, inject, onBeforeUnmount, ref, watch } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import axios from 'axios';
import Modal from '@/Components/UI/Modal.vue';
import { formatMoney } from '@/Utils/format';

const props = defineProps({ trip:Object, scenarios:Object });
const route = inject('route');
const name = ref('Nueva simulación');
const parameters = ref({ reference_rate:props.trip.tipo_cambio, expense_variation_percent:'0', products:[], expenses:[] });
const objective = ref(false);
const pricing = ref({method:'margin',target:'20',commission:'0',quantum:1});
const priceSelection = ref(props.trip.products.map(p => p.id));
const productId = ref('');
const expenseId = ref('');
const productDraft = ref({});
const expenseDraft = ref({});
const product = computed(() => props.trip.products.find(p => p.id === Number(productId.value)));
const expense = computed(() => props.trip.expenses.find(e => e.id === Number(expenseId.value)));
const result = ref(null);
const listing = ref(props.scenarios);
const errors = ref({});
const busy = ref(false);
const idempotencyKey = ref(null);
const confirming = ref(false);
const applyForm = useForm({});
const rowPage = ref(1);
const showOnlyChanged = ref(true);
let controller;
let sequence = 0;
const messages = computed(() => [...Object.values(errors.value).flat(),...Object.values(applyForm.errors).flat()]);
const rows = computed(() => (result.value?.result.lines || []).filter(line => !showOnlyChanged.value || line.cost_delta !== '0' || line.quantity_before !== line.quantity_after || line.price_before !== line.price_after));
const rowPages = computed(() => Math.max(1,Math.ceil(rows.value.length/20)));
const visibleRows = computed(() => rows.value.slice((rowPage.value-1)*20,rowPage.value*20));
const canApply = computed(() => !!result.value?.scenario && !result.value.scenario.applied_at && result.value.revision === props.trip.revision && props.trip.estado === 'activo');
const deltaClass = value => String(value).startsWith('-') ? 'f-positive' : String(value) === '0' ? '' : 'f-error-text';
const signed = value => String(value).startsWith('-') ? '-$'+formatMoney(String(value).slice(1)) : (String(value) === '0' ? '$' : '+$')+formatMoney(value);
const invalidate = (clearErrors = true) => {
    sequence++;
    controller?.abort(); busy.value = false;
    result.value = null; idempotencyKey.value = null; confirming.value = false; rowPage.value = 1;
    if (clearErrors) { errors.value = {}; applyForm.clearErrors(); }
};
watch([parameters,objective,pricing,priceSelection],()=>invalidate(),{deep:true});
watch(name,()=>idempotencyKey.value = null);
watch(showOnlyChanged,()=>rowPage.value = 1);
watch(() => props.scenarios,value => {
    listing.value = value;
    const active = value?.data.find(item => item.id === result.value?.scenario?.id);
    if (active) result.value.scenario = {...result.value.scenario,...active};
});
watch(() => props.trip.revision,()=>{
    if (!result.value?.scenario) invalidate(false);
    else { sequence++; controller?.abort(); busy.value = false; confirming.value = false; }
});
watch(productId,()=>{
    const p = product.value;
    productDraft.value = p ? {cantidad:p.cantidad,costo_unitario:p.costo_unitario,weight:p.weight ?? '',volume:p.volume ?? '',precio_venta:p.precio_venta ?? ''} : {};
});
watch(expenseId,()=>{
    const e = expense.value;
    expenseDraft.value = e ? {monto:e.monto,allocation_method:e.allocation_method,target_product_id:e.target_product_id ?? ''} : {};
});
onBeforeUnmount(()=>controller?.abort());
const editProduct = () => {
    if (!product.value) return;
    const p = product.value;
    const draft = {...productDraft.value,id:p.id,weight:productDraft.value.weight === '' ? null : productDraft.value.weight,volume:productDraft.value.volume === '' ? null : productDraft.value.volume};
    if (objective.value) delete draft.precio_venta;
    else draft.precio_venta = draft.precio_venta === '' ? null : draft.precio_venta;
    parameters.value.products = [...parameters.value.products.filter(row=>row.id !== p.id),draft];
    productId.value = '';
};
const editExpense = () => {
    if (!expense.value) return;
    const draft = {...expenseDraft.value,id:expense.value.id};
    if (expense.value.rate_mode === 'paid') delete draft.monto;
    draft.target_product_id = draft.allocation_method === 'direct' ? (draft.target_product_id || null) : null;
    parameters.value.expenses = [...parameters.value.expenses.filter(row=>row.id !== draft.id),draft];
    expenseId.value = '';
};
const payload = () => ({...JSON.parse(JSON.stringify(parameters.value)),products:parameters.value.products.map(row=>{
    const data = {...row}; if (objective.value) delete data.precio_venta; return data;
}),pricing:objective.value ? {...pricing.value,product_ids:priceSelection.value} : null,expected_revision:props.trip.revision});
const generate = async () => {
    invalidate(); const current = sequence; controller = new AbortController(); busy.value = true;
    try {
        const response = await axios.post(route('trips.scenarios.preview',props.trip.id),payload(),{signal:controller.signal,headers:{Accept:'application/json'}});
        if (current === sequence) { result.value = {...response.data,scenario:null}; idempotencyKey.value = crypto.randomUUID(); }
    } catch(error) {
        if (current === sequence && !axios.isCancel(error)) errors.value = error.response?.data?.errors || {scenario:'No se pudo simular. Revisa la conexión y vuelve a intentarlo.'};
    } finally { if (current === sequence) busy.value = false; }
};
const save = async () => {
    if (!result.value || result.value.scenario || busy.value) return;
    idempotencyKey.value ||= crypto.randomUUID();
    const current = sequence; controller = new AbortController(); busy.value = true; errors.value = {};
    try {
        const response = await axios.post(route('trips.scenarios.store',props.trip.id),{...payload(),name:name.value,idempotency_key:idempotencyKey.value},{signal:controller.signal,headers:{Accept:'application/json'}});
        if (current === sequence) {
            const scenario = response.data.scenario;
            result.value = {revision:scenario.base_revision,result:scenario.result,scenario};
            router.reload({only:['scenarios'],preserveScroll:true});
        }
    } catch(error) {
        if (current === sequence && !axios.isCancel(error)) errors.value = error.response?.data?.errors || {scenario:'No se pudo conservar el escenario. Reintenta sin cambiar los parámetros.'};
    } finally { if (current === sequence) busy.value = false; }
};
const view = async scenario => {
    invalidate(); const current = sequence; controller = new AbortController(); busy.value = true;
    try {
        const response = await axios.get(route('trips.scenarios.show',[props.trip.id,scenario.id]),{signal:controller.signal,headers:{Accept:'application/json'}});
        if (current === sequence) result.value = {revision:scenario.base_revision,result:response.data.scenario.result,scenario:response.data.scenario};
    } catch(error) {
        if (current === sequence && !axios.isCancel(error)) errors.value = {scenario:'No se pudo abrir la simulación conservada.'};
    } finally { if (current === sequence) busy.value = false; }
};
const nextPage = async page => {
    try { listing.value = (await axios.get(route('trips.scenarios.index',props.trip.id),{params:{page},headers:{Accept:'application/json'}})).data; }
    catch { errors.value = {scenario:'No se pudo cambiar de página. Intenta nuevamente.'}; }
};
const copy = () => {
    const saved = result.value?.scenario; if (!saved) return;
    const input = saved.input;
    parameters.value = {reference_rate:input.reference_rate ?? props.trip.tipo_cambio,expense_variation_percent:input.expense_variation_percent ?? '0',products:input.products || [],expenses:input.expenses || []};
    objective.value = !!input.pricing;
    pricing.value = input.pricing ? {...input.pricing} : {method:'margin',target:'20',commission:'0',quantum:1};
    priceSelection.value = input.pricing?.product_ids || props.trip.products.map(p=>p.id);
    name.value = `Copia de ${saved.name}`;
};
const apply = () => {
    if (!canApply.value || applyForm.processing) return;
    applyForm.transform(()=>({expected_revision:result.value.revision})).post(route('trips.scenarios.apply',[props.trip.id,result.value.scenario.id]),{
        preserveScroll:true,onSuccess:()=>confirming.value = false,onError:()=>confirming.value = false,
    });
};
</script>

<template>
    <div class="f-notice">Las simulaciones permanecen separadas de la importación. La tasa y la variación de gastos afectan estimaciones; los montos y tasas pagados se conservan. Guardar un escenario no modifica los costos reales.</div>
    <section class="f-panel"><div class="f-panel-heading"><div><h2>Crear simulación</h2><p class="f-hint">Base recalculada con la política vigente · revisión {{ trip.revision }}</p></div><span class="f-status">{{ trip.estado === 'activo' ? 'Puede aplicarse después de revisar' : 'Importación cerrada · solo simular' }}</span></div>
        <div class="f-panel-body"><form @submit.prevent="generate"><fieldset :disabled="busy || applyForm.processing"><legend class="sr-only">Parámetros del escenario</legend>
            <div class="f-form-grid"><div class="f-field"><label for="scenario-rate">Tasa de referencia alternativa · CLP / {{ trip.moneda_trabajo }}</label><input id="scenario-rate" class="f-input" type="number" v-model="parameters.reference_rate" min="0.00000001" max="1000000" step="0.00000001" required /></div><div class="f-field"><label for="scenario-expenses">Variación de gastos estimados · %</label><input id="scenario-expenses" class="f-input" type="number" v-model="parameters.expense_variation_percent" min="-100" max="1000" step="0.01" required /></div></div>
            <details style="margin-top:20px"><summary style="cursor:pointer;font-weight:600">Ajustes por producto · {{ parameters.products.length }}</summary><p class="f-hint" style="margin:8px 0">Estos ajustes son borradores del escenario. Las compras pagadas conservan cantidad y costo de origen.</p><div class="f-field"><label for="scenario-product">Producto para ajustar</label><select id="scenario-product" class="f-input" v-model="productId"><option value="">Seleccionar producto</option><option v-for="p in trip.products" :key="p.id" :value="p.id">{{ p.nombre }}</option></select></div>
                <div v-if="product" class="f-draft" style="margin-top:12px"><div class="f-form-grid"><div class="f-field"><label for="scenario-quantity">Cantidad alternativa</label><input id="scenario-quantity" class="f-input" type="number" v-model="productDraft.cantidad" min="1" max="1000000" step="1" :disabled="product.rate_mode === 'paid'" /></div><div class="f-field"><label for="scenario-unit">Costo unitario alternativo · {{ product.moneda }}</label><input id="scenario-unit" class="f-input" type="number" v-model="productDraft.costo_unitario" min="0" step="0.01" :disabled="product.rate_mode === 'paid'" /></div><div class="f-field"><label for="scenario-weight">Peso total alternativo · kg</label><input id="scenario-weight" class="f-input" type="number" v-model="productDraft.weight" min="0" step="0.00000001" /></div><div class="f-field"><label for="scenario-volume">Volumen total alternativo · m³</label><input id="scenario-volume" class="f-input" type="number" v-model="productDraft.volume" min="0" step="0.00000001" /></div><div v-if="!objective" class="f-field"><label for="scenario-price">Precio de venta alternativo · CLP / unidad</label><input id="scenario-price" class="f-input" type="number" v-model="productDraft.precio_venta" min="0.01" step="0.01" placeholder="Pendiente" /></div></div><button class="f-button" type="button" style="margin-top:12px" @click="editProduct">Agregar ajuste al escenario</button></div>
                <div v-for="row in parameters.products" :key="row.id" class="f-breakdown-row"><span>{{ trip.products.find(p=>p.id === row.id)?.nombre || `Producto ${row.id}` }} · {{ row.cantidad }} unidades · {{ row.costo_unitario }} original</span><button class="f-button" type="button" @click="parameters.products = parameters.products.filter(p=>p.id !== row.id)">Quitar ajuste</button></div>
            </details>
            <details style="margin-top:20px"><summary style="cursor:pointer;font-weight:600">Ajustes por gasto · {{ parameters.expenses.length }}</summary><p class="f-hint" style="margin:8px 0">Un monto alternativo reemplaza la variación porcentual de ese gasto. También puedes comparar reglas de reparto.</p><div class="f-field"><label for="scenario-expense">Gasto para ajustar</label><select id="scenario-expense" class="f-input" v-model="expenseId"><option value="">Seleccionar gasto</option><option v-for="e in trip.expenses" :key="e.id" :value="e.id">{{ e.concepto }}</option></select></div>
                <div v-if="expense" class="f-draft" style="margin-top:12px"><div class="f-form-grid"><div class="f-field"><label for="scenario-amount">Monto alternativo · {{ expense.moneda }}</label><input id="scenario-amount" class="f-input" type="number" v-model="expenseDraft.monto" min="0" step="0.01" :disabled="expense.rate_mode === 'paid'" /></div><div class="f-field"><label for="scenario-rule">Regla alternativa de reparto</label><select id="scenario-rule" class="f-input" v-model="expenseDraft.allocation_method"><option value="value">Valor de compra</option><option value="units">Unidades</option><option value="weight">Peso</option><option value="volume">Volumen</option><option value="direct">Producto directo</option></select></div><div v-if="expenseDraft.allocation_method === 'direct'" class="f-field"><label for="scenario-target">Producto que recibe el gasto</label><select id="scenario-target" class="f-input" v-model="expenseDraft.target_product_id"><option value="">Seleccionar producto</option><option v-for="p in trip.products" :key="p.id" :value="p.id">{{ p.nombre }}</option></select></div></div><button class="f-button" type="button" style="margin-top:12px" @click="editExpense">Agregar ajuste de gasto</button></div>
                <div v-for="row in parameters.expenses" :key="row.id" class="f-breakdown-row"><span>{{ trip.expenses.find(e=>e.id === row.id)?.concepto || `Gasto ${row.id}` }} · {{ row.monto ?? 'Monto histórico' }} · {{ row.allocation_method }}</span><button class="f-button" type="button" @click="parameters.expenses = parameters.expenses.filter(e=>e.id !== row.id)">Quitar ajuste</button></div>
            </details>
            <label style="display:flex;align-items:center;gap:10px;min-height:44px;margin-top:16px"><input type="checkbox" v-model="objective" /> Calcular precios por objetivo en este escenario</label>
            <div v-if="objective" class="f-draft"><p class="f-hint" style="margin-bottom:12px">Se reemplazan los precios de la selección por el objetivo; la comisión guía el cálculo y no se agrega automáticamente al costo del lote.</p><div class="f-form-grid"><div class="f-field"><label for="scenario-price-method">Objetivo comercial</label><select id="scenario-price-method" class="f-input" v-model="pricing.method"><option value="margin">Margen sobre venta</option><option value="markup">Recargo sobre costo</option></select></div><div class="f-field"><label for="scenario-price-target">Objetivo · %</label><input id="scenario-price-target" class="f-input" type="number" v-model="pricing.target" min="0" max="1000" step="0.01" required /></div><div class="f-field"><label for="scenario-commission">Comisión de venta · %</label><input id="scenario-commission" class="f-input" type="number" v-model="pricing.commission" min="0" max="99.99" step="0.01" required /></div><div class="f-field"><label for="scenario-quantum">Redondeo hacia arriba</label><select id="scenario-quantum" class="f-input" v-model="pricing.quantum"><option :value="1">Al peso</option><option :value="10">A 10 CLP</option><option :value="100">A 100 CLP</option><option :value="1000">A 1.000 CLP</option></select></div></div><div style="max-height:220px;overflow:auto;margin-top:12px"><label v-for="p in trip.products" :key="p.id" style="display:flex;align-items:center;gap:10px;min-height:44px"><input type="checkbox" :value="p.id" v-model="priceSelection" /><span>{{ p.nombre }}</span></label></div></div>
            <div class="f-actions" style="margin-top:20px"><button class="f-button f-button-primary" type="submit">{{ busy ? 'Calculando…' : 'Simular y comparar' }}</button><span class="f-hint">No cambia los costos reales.</span></div>
        </fieldset></form></div>
    </section>
    <div v-if="messages.length" class="f-notice f-notice-error" role="alert"><p v-for="(message,index) in messages" :key="index">{{ message }}</p><button v-if="errors.conflict || applyForm.errors.conflict" class="f-button" @click="router.reload({preserveScroll:true})">Recargar revisión</button></div>
    <section v-if="result" class="f-panel"><div class="f-panel-heading"><div><h2>{{ result.scenario?.name || 'Comparación de la simulación' }}</h2><p class="f-hint">Base: revisión {{ result.revision }} · actual: {{ trip.revision }} · cifras de la simulación conservadas</p></div><span v-if="result.scenario?.applied_at" class="f-status">Aplicado · revisión {{ result.scenario.applied_revision }}</span></div><div class="f-panel-body">
        <div class="f-form-grid"><article class="f-metric"><span class="f-metric-label">Costo de la base · CLP</span><strong class="f-metric-value">${{ formatMoney(result.result.before.cost) }}</strong><small>Tasa {{ result.result.before.reference_rate }}</small></article><article class="f-metric f-metric-accent"><span class="f-metric-label">Costo simulado · CLP</span><strong class="f-metric-value">${{ formatMoney(result.result.after.cost) }}</strong><small>Tasa {{ result.result.after.reference_rate }}</small></article></div>
        <div class="f-breakdown-row"><span>Diferencia de costo</span><strong :class="deltaClass(result.result.cost_delta)">{{ signed(result.result.cost_delta) }}</strong></div>
        <div class="f-breakdown-row"><span>Utilidad antes de comisión · base → simulación</span><strong>{{ result.result.before.projection.profit === null ? 'Pendiente' : '$'+formatMoney(result.result.before.projection.profit) }} → {{ result.result.after.projection.profit === null ? 'Pendiente' : '$'+formatMoney(result.result.after.projection.profit) }}</strong></div>
        <div v-if="result.result.pricing" class="f-breakdown-row"><span>Utilidad tras comisión · productos seleccionados</span><strong>${{ formatMoney(result.result.pricing.totals.profit_after_commission,2) }}</strong></div>
        <p class="f-hint" style="margin:12px 0">Se conservan {{ result.result.paid_products_preserved }} compras y {{ result.result.paid_expenses_preserved }} gastos pagados. La proyección requiere precios completos; no representa ventas realizadas.</p>
        <div v-if="result.result.after.unassigned !== '0'" class="f-notice f-notice-error">Quedan ${{ formatMoney(result.result.after.unassigned) }} sin asignar. Este escenario podrá guardarse, pero la importación no podrá cerrarse hasta completar el reparto.</div>
        <label style="display:flex;gap:10px;align-items:center;min-height:44px"><input type="checkbox" v-model="showOnlyChanged" /> Mostrar solo líneas con diferencias</label>
        <div class="f-table-scroll f-desktop-table"><table class="f-table"><caption class="sr-only">Diferencias por producto en el escenario</caption><thead><tr><th>Producto</th><th class="f-numeric">Costo base</th><th class="f-numeric">Costo simulado</th><th class="f-numeric">Diferencia</th><th class="f-numeric">Precio simulado</th></tr></thead><tbody><tr v-for="line in visibleRows" :key="line.id"><td><strong class="f-table-title">{{ line.name }}</strong><span class="f-hint">{{ line.quantity_before }} → {{ line.quantity_after }} unidades</span></td><td class="f-numeric">${{ formatMoney(line.cost_before) }}</td><td class="f-numeric">${{ formatMoney(line.cost_after) }}</td><td class="f-numeric">{{ signed(line.cost_delta) }}</td><td class="f-numeric">{{ line.price_after === null ? 'Pendiente' : '$'+formatMoney(line.price_after,2) }}</td></tr></tbody></table></div>
        <div class="f-mobile-cards"><article v-for="line in visibleRows" :key="line.id" class="f-mobile-card"><strong>{{ line.name }}</strong><p class="f-hint">{{ line.quantity_before }} → {{ line.quantity_after }} unidades</p><div class="f-mobile-card-bottom"><span>Costo base → simulado</span><strong>${{ formatMoney(line.cost_before) }} → ${{ formatMoney(line.cost_after) }}</strong></div><div class="f-mobile-card-bottom"><span>Diferencia</span><strong>{{ signed(line.cost_delta) }}</strong></div></article></div>
        <p v-if="!rows.length" class="f-hint">No hay diferencias en las líneas de esta comparación.</p><div v-if="rowPages > 1" class="f-pagination"><span>{{ rowPage }} / {{ rowPages }}</span><div class="f-actions"><button class="f-button" @click="rowPage--" :disabled="rowPage === 1">Anterior</button><button class="f-button" @click="rowPage++" :disabled="rowPage === rowPages">Siguiente</button></div></div>
        <div v-if="!result.scenario" class="f-field" style="margin-top:20px"><label for="scenario-name">Nombre para conservar el escenario</label><input id="scenario-name" class="f-input" v-model="name" maxlength="255" :disabled="busy" /><button class="f-button f-button-primary" @click="save" :disabled="busy">{{ busy ? 'Guardando…' : 'Guardar escenario separado' }}</button></div>
        <div v-else class="f-actions" style="margin-top:20px"><button v-if="canApply" class="f-button f-button-primary" @click="confirming = true">Revisar aplicación del escenario</button><button class="f-button" @click="copy" :disabled="busy">Usar parámetros en nueva simulación</button></div>
        <p v-if="result.scenario && !result.scenario.applied_at && result.revision !== trip.revision" class="f-notice" style="margin-top:16px">La revisión de origen cambió. Conserva esta comparación y genera una nueva simulación para aplicarla.</p>
    </div></section>
    <section class="f-panel"><div class="f-panel-heading"><h2>Escenarios conservados</h2><span class="f-hint">{{ listing?.total || 0 }} simulaciones</span></div><div class="f-panel-body"><article v-for="scenario in listing?.data || []" :key="scenario.id" class="f-breakdown-row" style="align-items:center;flex-wrap:wrap"><div><strong>{{ scenario.name }}</strong><p class="f-hint">Base {{ scenario.base_revision }} · {{ scenario.actor?.name || 'Proceso del sistema' }} · {{ new Date(scenario.created_at).toLocaleString('es-CL') }}</p><span class="f-status">{{ scenario.applied_revision ? `Aplicado en revisión ${scenario.applied_revision}` : scenario.base_revision === trip.revision ? 'Disponible para revisar' : 'Revisión anterior' }}</span></div><button class="f-button" @click="view(scenario)" :disabled="busy || applyForm.processing">Ver comparación</button></article><p v-if="!listing?.data.length" class="f-hint">Guarda una simulación para comparar decisiones sin cambiar la importación.</p><div v-if="listing?.last_page > 1" class="f-pagination"><span>{{ listing.current_page }} / {{ listing.last_page }}</span><div class="f-actions"><button class="f-button" @click="nextPage(listing.current_page-1)" :disabled="listing.current_page <= 1">Anteriores</button><button class="f-button" @click="nextPage(listing.current_page+1)" :disabled="listing.current_page >= listing.last_page">Siguientes</button></div></div></div></section>
    <Modal :show="confirming" title="Aplicar escenario como nueva revisión" :closeable="!applyForm.processing" @close="confirming = false"><p v-if="result">Se aplicarán la tasa, los ajustes de productos/gastos y los precios de «{{ result.scenario?.name }}» sobre la revisión {{ result.revision }}. El costo cambiará de ${{ formatMoney(result.result.before.cost) }} a ${{ formatMoney(result.result.after.cost) }}. Las operaciones pagadas conservarán sus datos y la simulación original seguirá disponible.</p><div class="f-actions" style="margin-top:20px"><button class="f-button" @click="confirming = false" :disabled="applyForm.processing">Cancelar</button><button class="f-button f-button-primary" @click="apply" :disabled="applyForm.processing">{{ applyForm.processing ? 'Aplicando…' : 'Aplicar escenario' }}</button></div></Modal>
</template>
