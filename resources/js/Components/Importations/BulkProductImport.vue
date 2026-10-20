<script setup lang="ts">
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import axios from 'axios';
import Modal from '@/Components/UI/Modal.vue';
import { formatMoney } from '@/Utils/format';

import { useRoute } from '@/Composables/useRoute';
import { inputFile } from '@/Utils/input';
import { bulkRequest, bulkMultipart } from '@/Utils/advancedDrafts';
import { requestErrors, validationErrors } from '@/Utils/validation';
import type { ImportDetail, RevisionMutation } from '@/Types/financial';
import type { BulkSettings, BulkPreview, BulkSource, BulkApplyMutation, ImportColumn, ErrorBag } from '@/Types/advanced';
const props = defineProps<{ trip: ImportDetail }>();
const route = useRoute();
const open = ref(false);
const mode = ref('paste');
const text = ref('');
const file = ref<File | null>(null);
const settings = ref<BulkSettings>({ delimiter: 'tab', has_header: true, default_currency: props.trip.moneda_trabajo,
    mapping: { nombre: 0, cantidad: 1, moneda: 2, costo_unitario: 3, weight: null, volume: null } });
const fields: { key: ImportColumn; label: string; required?: boolean }[] = [{ key:'nombre', label:'Producto', required:true }, { key:'cantidad', label:'Cantidad', required:true }, { key:'moneda', label:'Moneda original' }, { key:'costo_unitario', label:'Costo unitario original', required:true }, { key:'weight', label:'Peso total de la línea · kg' }, { key:'volume', label:'Volumen total de la línea · m³' }];
const columns = ref(Array.from({length:32}, (_, index) => ({index, label:`Columna ${index+1}`})));
const preview = ref<BulkPreview | null>(null);
const payload = ref<BulkApplyMutation | null>(null);
const pending = ref(false);
const errors = ref<ErrorBag>({});
const page = ref(1);
const invalidOnly = ref(false);
const confirming = ref(false);
const applyForm = useForm<RevisionMutation>({expected_revision:props.trip.revision});
const applyErrors = computed(()=>validationErrors(applyForm.errors));
let controller: AbortController | undefined;
let sequence = 0;
const filteredRows = computed(() => (preview.value?.rows || []).filter(row => !invalidOnly.value || Object.keys(row.errors).length));
const pages = computed(() => Math.max(1, Math.ceil(filteredRows.value.length / 20)));
const visibleRows = computed(() => filteredRows.value.slice((page.value-1)*20, page.value*20));
const messages = computed(() => [...Object.values(errors.value).flat(), ...Object.values(applyErrors.value).flat(), ...Object.values(preview.value?.cost_errors || {}).flat()]);
const invalidate = (clearErrors = true) => {
    sequence++;
    controller?.abort();
    pending.value = false;
    preview.value = null;
    payload.value = null;
    confirming.value = false;
    if (clearErrors) { errors.value = {}; applyForm.clearErrors(); }
    page.value = 1;
};
watch([text, file, mode, settings], () => invalidate(), {deep:true});
watch(() => props.trip.revision, () => invalidate(false));
watch(invalidOnly, () => page.value = 1);
onBeforeUnmount(() => controller?.abort());
const example = () => {
    mode.value = 'paste';
    settings.value.delimiter = 'tab';
    settings.value.has_header = true;
    settings.value.mapping = { nombre:0, cantidad:1, moneda:2, costo_unitario:3, weight:null, volume:null };
    text.value = `nombre\tcantidad\tmoneda\tcosto_unitario\nEquipo portátil\t3\t${props.trip.moneda_trabajo}\t100.00\nSensor de precisión\t2\t${props.trip.moneda_trabajo}\t50.00`;
};
const generate = async () => {
    invalidate();
    const current = sequence;
    controller = new AbortController();
    pending.value = true;
    if (mode.value === 'file' && !file.value) {
        pending.value = false; errors.value = {file:'Selecciona el archivo que quieres revisar.'}; return;
    }
    const source: BulkSource = mode.value === 'file' && file.value ? {file:file.value} : {source_text:text.value};
    const data = bulkRequest(settings.value,source,props.trip.revision);
    try {
        const body = data.file ? bulkMultipart(data) : data;
        const result = await axios.post<BulkPreview>(route('trips.products.import.preview',props.trip.id),body,{signal:controller.signal,headers:{Accept:'application/json'}});
        if (current === sequence) {
            preview.value = result.data;
            columns.value = result.data.columns;
            payload.value = {...data,idempotency_key:crypto.randomUUID()};
        }
    } catch (error) {
        if (current === sequence && !axios.isCancel(error)) errors.value = requestErrors(error,'import','No se pudo revisar el lote. Comprueba la conexión y vuelve a intentarlo.');
    } finally {
        if (current === sequence) pending.value = false;
    }
};
const apply = () => {
    if (!preview.value?.can_apply || !payload.value || applyForm.processing) return;
    const request = payload.value;
    applyForm.transform(() => request).post(route('trips.products.import.apply',props.trip.id), {
        preserveScroll:true,
        onSuccess:() => { invalidate(); text.value = ''; file.value = null; open.value = false; },
        onError:() => confirming.value = false,
    });
};
</script>

<template>
    <section class="f-panel">
        <div class="f-panel-heading"><div><h2>Importar productos por lote</h2><p class="f-hint">Pega desde una hoja de cálculo o carga un CSV. Hasta 1.000 filas.</p></div><button class="f-button" :aria-expanded="open" @click="open = !open" :disabled="applyForm.processing">{{ open ? 'Ocultar importador' : 'Pegar / importar CSV' }}</button></div>
        <div v-if="open" class="f-panel-body">
            <div class="f-notice">El lote se agrega completo o se rechaza completo. Los números pueden ir como en Chile (1.250,50) o con punto decimal (1250.50), con cantidades enteras y la moneda original de esta importación o CLP. No se reemplazan productos existentes.</div>
            <form @submit.prevent="generate">
                <fieldset :disabled="pending || applyForm.processing">
                    <legend class="sr-only">Datos de la carga por lote</legend>
                    <div class="f-form-grid">
                        <div class="f-field"><label for="bulk-mode">Origen</label><select id="bulk-mode" class="f-input" v-model="mode"><option value="paste">Texto pegado</option><option value="file">Archivo CSV</option></select></div>
                        <div class="f-field"><label for="bulk-delimiter">Separador</label><select id="bulk-delimiter" class="f-input" v-model="settings.delimiter"><option value="tab">Tabulador · hoja de cálculo</option><option value=",">Coma</option><option value=";">Punto y coma</option></select></div>
                    </div>
                    <div v-if="mode === 'paste'" class="f-field" style="margin-top:16px"><label for="bulk-text">Filas para importar</label><textarea id="bulk-text" class="f-input" v-model="text" rows="6" placeholder="Producto, cantidad, moneda y costo unitario en columnas separadas" required spellcheck="false" /><button class="f-button" type="button" style="justify-self:start" @click="example">Usar ejemplo</button></div>
                    <div v-else class="f-field" style="margin-top:16px"><label for="bulk-file">Archivo CSV · UTF-8 · máximo 2 MB</label><input id="bulk-file" class="f-input" type="file" accept=".csv,.txt,text/csv,text/plain" @change="file = inputFile($event)" required /></div>
                    <label style="display:flex;gap:10px;align-items:center;min-height:44px;margin-top:12px"><input type="checkbox" v-model="settings.has_header" /> La primera fila contiene encabezados</label>
                    <div class="f-form-grid" style="margin-top:12px">
                        <div v-for="field in fields" :key="field.key" class="f-field"><label :for="`bulk-map-${field.key}`">{{ field.label }}{{ field.required ? ' · obligatorio' : '' }}</label><select :id="`bulk-map-${field.key}`" class="f-input" v-model="settings.mapping[field.key]"><option v-if="!field.required" :value="null">{{ field.key === 'moneda' ? 'Usar moneda por defecto' : 'Sin columna' }}</option><option v-for="column in columns" :key="column.index" :value="column.index">{{ column.index + 1 }} · {{ column.label }}</option></select></div>
                        <div class="f-field"><label for="bulk-currency">Moneda por defecto</label><select id="bulk-currency" class="f-input" v-model="settings.default_currency"><option :value="trip.moneda_trabajo">{{ trip.moneda_trabajo }}</option><option value="CLP">CLP</option></select></div>
                    </div>
                    <div class="f-actions" style="margin-top:16px"><button type="submit" class="f-button f-button-primary">{{ pending ? 'Revisando…' : 'Revisar lote' }}</button><span class="f-hint">Todavía no guarda productos ni costos.</span></div>
                </fieldset>
            </form>
            <div v-if="messages.length" class="f-notice f-notice-error" role="alert" style="margin-top:16px"><p v-for="(message,index) in messages" :key="index">{{ message }}</p><button v-if="errors.conflict || applyErrors.conflict" class="f-button" @click="router.reload()">Recargar revisión</button></div>
            <template v-if="preview">
                <div class="f-panel-heading" style="padding:20px 0 12px"><div><h3>Vista previa · revisión {{ preview.revision }}</h3><p class="f-hint">{{ preview.count }} filas · {{ preview.invalid_count }} con errores</p></div><label><input type="checkbox" v-model="invalidOnly" /> Solo errores</label></div>
                <div class="f-table-scroll f-desktop-table"><table class="f-table"><caption class="sr-only">Productos del lote y errores por fila</caption><thead><tr><th>Fila / producto</th><th class="f-numeric">Cantidad</th><th class="f-numeric">Unitario original</th><th>Validación</th></tr></thead><tbody><tr v-for="row in visibleRows" :key="row.row"><td><span class="f-hint">Fila {{ row.row }}</span><strong class="f-table-title">{{ row.data.nombre || 'Sin nombre' }}</strong></td><td class="f-numeric">{{ row.data.cantidad }}</td><td class="f-numeric">{{ row.data.costo_unitario }} {{ row.data.moneda }}</td><td><span v-if="!Object.keys(row.errors).length" class="f-status">Válida</span><p v-for="(error,index) in Object.values(row.errors).flat()" :key="index" class="f-error-text">{{ error }}</p></td></tr></tbody></table></div>
                <div class="f-mobile-cards"><article v-for="row in visibleRows" :key="row.row" class="f-mobile-card"><span class="f-hint">Fila {{ row.row }}</span><strong class="f-table-title">{{ row.data.nombre || 'Sin nombre' }}</strong><p class="f-hint">{{ row.data.cantidad }} unidades · {{ row.data.costo_unitario }} {{ row.data.moneda }}</p><span v-if="!Object.keys(row.errors).length" class="f-status">Válida</span><p v-for="(error,index) in Object.values(row.errors).flat()" :key="index" class="f-error-text">{{ error }}</p></article></div>
                <div class="f-pagination"><span>Página {{ page }} de {{ pages }} · {{ filteredRows.length }} filas</span><div class="f-actions"><button class="f-button" @click="page--" :disabled="page <= 1">Anterior</button><button class="f-button" @click="page++" :disabled="page >= pages">Siguiente</button></div></div>
                <div v-if="preview.totals" class="f-notice" style="margin-top:16px">Costo del lote completo después de importar: <strong>${{ formatMoney(preview.totals.total) }}</strong> CLP. Compra ${{ formatMoney(preview.totals.purchase) }} · gastos ${{ formatMoney(preview.totals.expenses) }} · sin asignar ${{ formatMoney(preview.totals.unassigned) }}.</div>
                <div v-if="!preview.can_apply" class="f-notice f-notice-error" role="status">Corrige los errores y revisa nuevamente el lote. No se ha guardado ninguna fila.</div>
                <div class="f-actions" style="margin-top:16px"><button class="f-button f-button-primary" @click="confirming = true" :disabled="!preview.can_apply || applyForm.processing">Importar {{ preview.count }} productos</button><button class="f-button" @click="preview = null">Descartar vista previa</button></div>
            </template>
        </div>
    </section>
    <Modal :show="confirming" title="Importar lote de productos" :closeable="!applyForm.processing" @close="confirming = false"><p v-if="preview">Se agregarán {{ preview.count }} productos a la revisión {{ preview.revision }} y se recalcularán los costos. El lote completo debe ser válido. Un reintento del mismo envío no duplicará productos.</p><div class="f-actions" style="margin-top:20px"><button class="f-button" @click="confirming = false" :disabled="applyForm.processing">Cancelar</button><button class="f-button f-button-primary" @click="apply" :disabled="applyForm.processing">{{ applyForm.processing ? 'Importando…' : 'Confirmar importación' }}</button></div></Modal>
</template>
