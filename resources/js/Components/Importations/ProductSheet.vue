<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import axios from 'axios';
import { formatMoney } from '@/Utils/format';
import { productDraft, compareProductDraft, mergeProductDraft } from '@/Utils/productConflict';
import Modal from '@/Components/UI/Modal.vue';
import BulkProductImport from './BulkProductImport.vue';
import ProductMetadataFields from './ProductMetadataFields.vue';
import ProductConflict from './ProductConflict.vue';
import CostBreakdownPanel from './CostBreakdownPanel.vue';
import { PlusIcon, PencilIcon, TrashIcon } from '@heroicons/vue/24/outline';
import { useRoute } from '@/Composables/useRoute';
import { inputValue } from '@/Utils/input';
import type { ImportDetail, ProductData, ProductDraft, ProductMutation, ProductRevision, MutationReceipt, ValidationFailure, CostRunSummary } from '@/Types/financial';
import { requestErrors, formFieldErrors } from '@/Utils/validation';
import type { ProductEditorDraft } from '@/Types/creation';
import type { ConflictChoices } from '@/Utils/productConflict';
const props = defineProps<{ trip: ImportDetail; latestRun: CostRunSummary | null; embedded?: boolean; draftState?: ProductEditorDraft | null }>();
const emit = defineEmits<{ delete: [product: ProductData]; draft: [state: ProductEditorDraft | null] }>();
const route = useRoute();
const editingId = ref<number | null>(null);
const adding = ref(false);
const detailId = ref<number | null>(null);
const pendingProduct = ref<ProductData | null>(null);
const base = ref<ProductDraft | null>(null);
const current = ref<ProductRevision | null>(null);
const choices = ref<ConflictChoices>({});
const saving = ref(false);
const loading = ref(false);
const unavailable = ref('');
const success = ref('');
let controller: AbortController | undefined;
let sequence = 0;
const selected = computed(()=>props.trip.products.find(p=>p.id === detailId.value));
let costTrigger: HTMLElement | null = null;
let costProductId: number | null = null;
const showCost = (id: number, event: MouseEvent) => {
    // Some macOS browsers do not focus buttons on pointer activation. The dialog
    // needs a focused opener to restore keyboard navigation when it closes.
    costTrigger = event.currentTarget instanceof HTMLElement ? event.currentTarget : null;
    costProductId = id;
    costTrigger?.focus();
    detailId.value = id;
};
const restoreCostFocus = () => nextTick(()=>{
    if (detailId.value !== null) return;
    const trigger = costTrigger?.isConnected && costTrigger.getClientRects().length ? costTrigger :
        [...document.querySelectorAll<HTMLElement>(`[data-product-cost="${costProductId}"]`)].find(node=>node.getClientRects().length);
    trigger?.focus();
});
const editable = computed(()=>props.trip.estado === 'activo');
const initial: ProductDraft = {nombre:'',moneda:props.trip.moneda_trabajo,cantidad:1,costo_unitario:'',weight:'',volume:'',rate_mode:'estimated',applied_rate:'',rate_source:'',rate_date:''};
const form = useForm<ProductMutation>({...initial,expected_revision:props.trip.revision});
const edit = useForm<ProductMutation>({...initial,expected_revision:props.trip.revision});
type RowErrors = Partial<Record<keyof ProductMutation | 'conflict' | 'session' | 'save', string>>;
const rowErrors = ref<RowErrors>({});
const comparison = computed(()=>current.value && base.value ? compareProductDraft(base.value,edit.data(),current.value.product) : []);
const draftChanged = computed(()=>base.value && compareProductDraft(base.value,edit.data(),base.value).length > 0);
const cancelRequest = () => { sequence++; controller?.abort(); loading.value = false; };
onBeforeUnmount(cancelRequest);
const focusEdit = (id: number | null) => nextTick(()=>[...document.querySelectorAll<HTMLElement>(`[data-product-edit="${id}"]`)].find(node=>node.getClientRects().length)?.focus());
const cancel = () => { if (saving.value) return; const id = editingId.value; cancelRequest(); editingId.value = null; edit.clearErrors(); rowErrors.value = {}; current.value = null; unavailable.value = ''; focusEdit(id); };
const activate = (product: ProductData) => { success.value = ''; cancelRequest(); editingId.value = product.id; edit.clearErrors(); rowErrors.value = {}; current.value = null; choices.value = {}; unavailable.value = ''; base.value = productDraft(product); Object.assign(edit,{...base.value,expected_revision:props.trip.revision}); };
const beginEdit = (product: ProductData) => {
    if (saving.value || embedSaving.value || adding.value || editingId.value === product.id) return;
    if (editingId.value && draftChanged.value) pendingProduct.value = product;
    else activate(product);
};
const loadCurrent = async () => {
    cancelRequest(); const request = sequence; controller = new AbortController(); loading.value = true; unavailable.value = '';
    try {
        const response = await axios.get<ProductRevision>(route('trips.products.show',[props.trip.id,editingId.value]),{signal:controller.signal,headers:{Accept:'application/json'}});
        if (request === sequence) { current.value = response.data; choices.value = {}; }
    } catch(error) {
        if (request === sequence && !axios.isCancel(error)) unavailable.value = (axios.isAxiosError(error) && error.response?.status === 404) ? 'El producto fue retirado o la importación está en la papelera. El borrador se conserva; no se guardará sobre un registro ausente.' : 'No se pudo consultar la versión actual. El borrador se conserva. Reintenta la comparación.';
    } finally { if (request === sequence) loading.value = false; }
};
const resolve = () => {
    if (!current.value?.editable || !base.value) return;
    try {
        const merged = mergeProductDraft(base.value,edit.data(),current.value.product,choices.value);
        base.value = productDraft(current.value.product);
        Object.assign(edit,{...merged,expected_revision:current.value.revision});
        current.value = null; choices.value = {}; edit.clearErrors(); rowErrors.value = {};
    } catch(error) { rowErrors.value = {...rowErrors.value, conflict: error instanceof Error ? error.message : 'Revisa los campos en conflicto.'}; }
};
const save = async (product: ProductData) => {
    if (saving.value || loading.value || current.value || unavailable.value || !editable.value || editingId.value !== product.id) return;
    saving.value = true; edit.clearErrors(); rowErrors.value = {};
    try {
        const id = editingId.value;
        const response = await axios.put<MutationReceipt>(route('trips.products.update',[props.trip.id,id]),edit.data(),{headers:{Accept:'application/json'}});
        success.value = `${edit.nombre} guardado en la revisión ${response.data.revision}.`;
        saving.value = false; cancel(); router.reload({onSuccess:()=>focusEdit(id)});
    } catch(error) {
        saving.value = false;
        const failure = axios.isAxiosError<ValidationFailure>(error) ? error : null;
        if (failure?.response?.status === 422) {
            const errors = Object.fromEntries(Object.entries(failure.response.data.errors || {}).map(([key,value])=>[key,Array.isArray(value) ? value[0] : value]));
            rowErrors.value = errors;
            if (errors.conflict) await loadCurrent();
        } else if ([403,404].includes(failure?.response?.status ?? 0)) {
            rowErrors.value.conflict = 'La importación o este producto ya no admite esta edición. Revisa su estado actual.'; await loadCurrent();
        } else if ([401,419].includes(failure?.response?.status ?? 0)) rowErrors.value.session = 'La sesión venció. Vuelve a ingresar en otra pestaña antes de reintentar; tu borrador sigue disponible aquí.';
        else rowErrors.value.save = 'No se pudo guardar. Conservamos tu borrador; revisa la conexión antes de reintentar.';
    }
};
const showAdd = () => { if (embedSaving.value || form.processing || editingId.value) return; adding.value = !adding.value; if (adding.value) { form.expected_revision = props.trip.revision; form.clearErrors(); } };
const embedSaving = ref(false);
const add = async () => {
    if (!props.embedded) { form.post(route('trips.products.store',props.trip.id),{preserveScroll:true,onSuccess:()=>{form.reset();adding.value = false;}}); return; }
    if (embedSaving.value) return;
    embedSaving.value = true; form.clearErrors();
    try {
        await axios.post<MutationReceipt>(route('trips.products.store',props.trip.id),form.data(),{headers:{Accept:'application/json'}});
        form.reset(); adding.value = false; router.reload();
    } catch(failure) { Object.assign(form.errors,formFieldErrors(requestErrors(failure,'save','No se pudo guardar el producto. Tu borrador se conserva.'))); }
    finally { embedSaving.value = false; }
};
if (props.draftState) {
    if (props.draftState.mode === 'add') { Object.assign(form,props.draftState.fields); adding.value = true; }
    else { Object.assign(edit,props.draftState.fields); editingId.value = props.draftState.id; base.value = props.draftState.base; }
}
watch(()=>({adding:adding.value,editing:editingId.value,form:form.data(),edit:edit.data(),base:base.value}),()=>{
    if (!props.embedded) return;
    emit('draft',adding.value ? {mode:'add',id:null,fields:{...form.data()},base:null} : editingId.value ? {mode:'edit',id:editingId.value,fields:{...edit.data()},base:base.value ? {...base.value} : null} : null);
},{deep:true});
</script>
<template>
    <div v-if="success" class="f-notice f-notice-success" role="status" style="overflow-wrap:anywhere">{{ success }}</div>
    <BulkProductImport v-if="editable && !embedded" :trip="trip" />
    <section class="f-panel"><div class="f-panel-heading"><div><h2>Hoja de productos</h2><p class="f-hint">{{ trip.products.length }} líneas · revisión {{ trip.revision }} · totales guardados</p></div><button v-if="editable" class="f-button f-button-primary" @click="showAdd" :disabled="!!editingId || embedSaving || saving"><PlusIcon /> Agregar producto</button></div>
        <form v-if="adding" class="f-panel-body" @submit.prevent="add"><fieldset class="f-form-grid" :disabled="embedSaving || form.processing"><div class="f-field"><label for="product-name">Producto</label><input id="product-name" class="f-input" v-model="form.nombre" required maxlength="255" /><span class="f-field-error">{{ form.errors.nombre }}</span></div><div class="f-field"><label for="product-currency">Moneda original</label><select id="product-currency" class="f-input" v-model="form.moneda"><option :value="trip.moneda_trabajo">{{ trip.moneda_trabajo }}</option><option value="CLP">CLP</option></select></div><div class="f-field"><label for="product-quantity">Cantidad</label><input id="product-quantity" class="f-input" type="number" v-model="form.cantidad" min="1" max="1000000" step="1" required /><span class="f-field-error">{{ form.errors.cantidad }}</span></div><div class="f-field"><label for="product-cost">Costo unitario original · {{ form.moneda }}</label><input id="product-cost" class="f-input" type="number" :value="form.costo_unitario" @input="form.costo_unitario = inputValue($event)" min="0" step="0.01" required /><span class="f-field-error">{{ form.errors.costo_unitario }}</span></div><div style="grid-column:1/-1"><ProductMetadataFields :form="form" :errors="form.errors" prefix="new-product" :reference-rate="trip.tipo_cambio" :working-currency="trip.moneda_trabajo" /></div><p class="f-hint">El borrador no modifica los totales hasta que guardes.</p><div class="f-actions"><button type="button" class="f-button" @click="adding = false">Cancelar</button><button type="submit" class="f-button f-button-primary" :disabled="form.processing || embedSaving">{{ form.processing || embedSaving ? 'Guardando…' : 'Guardar producto' }}</button></div></fieldset></form>
        <div v-if="adding && Object.keys(form.errors).length" class="f-notice f-notice-error" role="alert"><p v-for="(message,key) in form.errors" :key="key">{{ message }}</p><button v-if="('conflict' in form.errors && form.errors.conflict)" class="f-button" @click="form.expected_revision = trip.revision; form.clearErrors()">Revisar borrador con revisión {{ trip.revision }}</button></div>
        <div v-if="editingId" class="f-panel-body" @keydown.esc="cancel"><h3 style="font-weight:650;overflow-wrap:anywhere">Borrador de {{ edit.nombre }} · origen: revisión {{ edit.expected_revision }}</h3><p class="f-hint" style="margin:8px 0 16px">Los totales de la hoja siguen mostrando los datos guardados. Peso y volumen se refieren a toda la línea.</p><fieldset :disabled="saving || loading"><ProductMetadataFields :form="edit" :errors="rowErrors" prefix="edit-product" :reference-rate="trip.tipo_cambio" :working-currency="trip.moneda_trabajo" /></fieldset><div v-if="Object.keys(rowErrors).length" class="f-notice f-notice-error" role="alert" style="margin-top:16px"><p v-for="(message,key) in rowErrors" :key="key">{{ message }}</p></div><p v-if="loading" class="f-hint" role="status">Consultando la revisión actual…</p><div v-if="unavailable" class="f-notice f-notice-error" role="alert">{{ unavailable }}<button type="button" class="f-button" @click="loadCurrent" :disabled="loading">Reintentar comparación</button></div><ProductConflict v-if="current" :comparison="comparison" :choices="choices" :revision="current.revision" :editable="current.editable" :busy="loading" @refresh="loadCurrent" @resolve="resolve" /><div class="f-actions" style="margin-top:16px"><button class="f-button" type="button" @click="cancel" :disabled="saving">Cancelar borrador</button></div></div>
        <template v-if="trip.products.length">
            <div class="f-table-scroll f-desktop-table"><table class="f-table"><caption class="sr-only">Hoja editable de productos, costos guardados y trazabilidad</caption><thead><tr><th>Producto</th><th class="f-numeric">Unidades</th><th class="f-numeric">Unitario original</th><th class="f-numeric">Compra · CLP</th><th class="f-numeric">Gastos · CLP</th><th class="f-numeric">Costo unitario · CLP</th><th class="f-numeric">Total · CLP</th><th><span class="sr-only">Acciones</span></th></tr></thead><tbody><tr v-for="product in trip.products" :key="product.id" @keydown.esc="cancel"><td><template v-if="editingId === product.id"><input class="f-input" v-model="edit.nombre" :aria-label="`Nombre de ${product.nombre}`" @keydown.enter.prevent="save(product)" :disabled="saving" /><select class="f-input" v-model="edit.moneda" :disabled="saving" aria-label="Moneda original"><option :value="trip.moneda_trabajo">{{ trip.moneda_trabajo }}</option><option value="CLP">CLP</option></select><span class="f-field-error">{{ rowErrors.nombre }}</span></template><template v-else><button class="f-table-title" @click="showCost(product.id,$event)" :data-product-cost="product.id">{{ product.nombre }}</button><span class="f-table-subtitle">{{ product.moneda }} · {{ product.rate_mode === 'paid' ? 'Tasa histórica de pago' : 'Tasa estimada' }}</span></template></td><td class="f-numeric"><input v-if="editingId === product.id" class="f-input" style="width:85px" v-model="edit.cantidad" type="number" min="1" :aria-label="`Cantidad de ${product.nombre}`" @keydown.enter.prevent="save(product)" :disabled="saving" /><template v-else>{{ product.cantidad }}</template></td><td class="f-numeric"><input v-if="editingId === product.id" class="f-input" style="width:115px" :value="edit.costo_unitario" @input="edit.costo_unitario = inputValue($event)" type="number" min="0" step="0.01" :aria-label="`Costo original de ${product.nombre}`" @keydown.enter.prevent="save(product)" :disabled="saving" /><template v-else>{{ formatMoney(product.costo_unitario, 2) }} {{ product.moneda }}</template></td><td class="f-numeric">${{ formatMoney(product.subtotal_clp) }}</td><td class="f-numeric">${{ formatMoney(product.gastos_prorrateados_clp) }}</td><td class="f-numeric"><button @click="showCost(product.id,$event)" :data-product-cost="product.id" style="text-decoration:underline;text-decoration-style:dotted">${{ formatMoney(product.costo_final_unitario_clp, 2) }}</button></td><td class="f-numeric"><strong>${{ formatMoney(product.costo_final_total_clp) }}</strong></td><td><div v-if="editable" class="f-actions"><template v-if="editingId === product.id"><button class="f-button f-button-primary" @click="save(product)" :disabled="saving || loading || !!current || !!unavailable">Guardar</button><button class="f-button" @click="cancel">Cancelar</button></template><template v-else><button class="f-icon-button" @click="beginEdit(product)" :data-product-edit="product.id" :disabled="saving || embedSaving || adding" :aria-label="`Editar ${product.nombre}`"><PencilIcon /></button><button class="f-icon-button" @click="emit('delete', product)" :aria-label="`Eliminar ${product.nombre}`"><TrashIcon /></button></template></div></td></tr></tbody></table></div>
            <div class="f-mobile-cards"><article v-for="product in trip.products" :key="product.id" class="f-mobile-card"><div class="f-mobile-card-top"><div><button class="f-table-title" @click="showCost(product.id,$event)" :data-product-cost="product.id">{{ product.nombre }}</button><span class="f-table-subtitle">{{ product.cantidad }} unidades · {{ formatMoney(product.costo_unitario, 2) }} {{ product.moneda }} / unidad</span></div><button v-if="editable" class="f-icon-button" @click="beginEdit(product)" :data-product-edit="product.id" :disabled="saving || embedSaving || adding" :aria-label="`Editar ${product.nombre}`"><PencilIcon /></button></div><div v-if="editingId === product.id" class="f-form-grid f-draft" style="margin-top:16px" @keydown.esc="cancel"><div class="f-field"><label :for="`mobile-name-${product.id}`">Nombre</label><input :id="`mobile-name-${product.id}`" class="f-input" :disabled="saving" @keydown.enter.prevent="save(product)" v-model="edit.nombre" /></div><div class="f-field"><label :for="`mobile-currency-${product.id}`">Moneda</label><select :id="`mobile-currency-${product.id}`" class="f-input" v-model="edit.moneda" :disabled="saving"><option :value="trip.moneda_trabajo">{{ trip.moneda_trabajo }}</option><option value="CLP">CLP</option></select></div><div class="f-field"><label :for="`mobile-quantity-${product.id}`">Cantidad</label><input :id="`mobile-quantity-${product.id}`" class="f-input" :disabled="saving" @keydown.enter.prevent="save(product)" type="number" v-model="edit.cantidad" min="1" /></div><div class="f-field"><label :for="`mobile-cost-${product.id}`">Costo original</label><input :id="`mobile-cost-${product.id}`" class="f-input" :disabled="saving" @keydown.enter.prevent="save(product)" type="number" :value="edit.costo_unitario" @input="edit.costo_unitario = inputValue($event)" min="0" step="0.01" /></div><p class="f-hint">Los totales siguen mostrando el costo guardado.</p><div class="f-actions"><button class="f-button" @click="cancel">Cancelar</button><button class="f-button f-button-primary" @click="save(product)" :disabled="saving || loading || !!current || !!unavailable">Guardar</button><button class="f-button f-button-danger" @click="emit('delete',product)">Eliminar</button></div></div><div class="f-mobile-card-bottom"><span class="f-hint">Costo unitario final</span><button @click="showCost(product.id,$event)" :data-product-cost="product.id"><strong>${{ formatMoney(product.costo_final_unitario_clp, 2) }}</strong></button></div><div class="f-mobile-card-bottom" style="margin-top:8px"><span class="f-hint">Total de la línea · CLP</span><strong>${{ formatMoney(product.costo_final_total_clp) }}</strong></div></article></div>
        </template><div v-else class="f-empty"><h2>Aún no hay productos</h2><p>Agrega la compra en su moneda original. Los gastos se distribuirán al guardar.</p><button v-if="editable" class="f-button f-button-primary" @click="showAdd" :disabled="!!editingId || embedSaving || saving">Agregar primer producto</button></div>
    </section>
    <p class="f-footer-note">Enter guarda la fila en edición. Escape cancela el borrador. Selecciona cualquier costo unitario para ver su desglose. Los importes se distribuyen en CLP enteros según la regla de cada gasto; los residuos se asignan por orden estable.</p>
    <Modal :show="!!pendingProduct" title="Descartar borrador de producto" @close="pendingProduct = null"><p>El producto actual tiene cambios sin guardar. Si abres otro producto, se descartará ese borrador.</p><div class="f-actions" style="margin-top:16px"><button class="f-button" @click="pendingProduct = null">Seguir editando</button><button class="f-button f-button-danger" @click="pendingProduct && activate(pendingProduct); pendingProduct = null">Descartar y abrir otro producto</button></div></Modal>
    <CostBreakdownPanel :trip-id="trip.id" :current-revision="trip.revision" :product="selected ?? null" :run="latestRun" @close="detailId = null" @closed="restoreCostFocus" />
</template>
