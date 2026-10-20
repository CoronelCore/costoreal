<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, reactive, ref, watch } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import axios from 'axios';
import FinancialLayout from '@/Layouts/FinancialLayout.vue';
import ProductSheet from '@/Components/Importations/ProductSheet.vue';
import ExpensePanel from '@/Components/Importations/ExpensePanel.vue';
import ReviewPanel from '@/Components/Importations/ReviewPanel.vue';
import Modal from '@/Components/UI/Modal.vue';
import DecimalHint from '@/Components/UI/DecimalHint.vue';
import { useRoute } from '@/Composables/useRoute';
import { creationContext, copyCreationPayload, captureDraftWrite } from '@/Utils/creationDraft';
import { DraftSaveQueue } from '@/Utils/DraftSaveQueue';
import { requestErrors, validationErrors } from '@/Utils/validation';
import { formatMoney, formatDateOnly, formatRate } from '@/Utils/format';
import type { CreationProps, CreationDraft, CreationStep, CreationPayload, CreationContext, DraftWrite, ProductEditorDraft, ExpenseEditorDraft } from '@/Types/creation';
import type { ExpenseData, ProductData } from '@/Types/financial';
const props = defineProps<CreationProps>();
const route = useRoute();
const draft = ref<CreationDraft | null>(props.draft);
const clientId = ref(props.draft?.id ?? crypto.randomUUID());
const payload = reactive<CreationPayload>(props.draft ? copyCreationPayload(props.draft.payload) : {context:creationContext(null,props.selectedCompanyId),product:null,expense:null});
const step = ref<CreationStep>(props.draft?.step ?? 'contexto');
const steps: {id:CreationStep;label:string;description:string}[] = [{id:'contexto',label:'Contexto',description:'Empresa, fechas y moneda'},{id:'productos',label:'Productos',description:'Compra y cantidades'},{id:'gastos',label:'Gastos',description:'Conversión y distribución'},{id:'revision',label:'Revisión',description:'Costo, pendientes y cierre'}];
const context = useForm<CreationContext>(creationContext(payload.context,props.selectedCompanyId));
const saving = ref(false);
const actionBusy = ref(false);
const error = ref('');
const actionError = ref('');
const conflict = ref(false);
const stepHeading = ref<HTMLElement | null>(null);
const confirmation = ref<{title:string;message:string;action:()=>void} | null>(null);
let timer: ReturnType<typeof setTimeout> | undefined;
let mounted = true;
let pending: DraftWrite | null = null;
const saved = ref(JSON.stringify({step:step.value,payload:copyCreationPayload(payload)}));
const saveQueue = new DraftSaveQueue();
const representation = () => JSON.stringify({step:step.value,payload:copyCreationPayload(payload)});
const dirty = computed(()=>representation() !== saved.value);
const hasEditor = computed(()=>!!payload.product || !!payload.expense);
const status = computed(()=>conflict.value ? 'Conflicto · cambios locales conservados' : error.value ? 'No se pudo guardar el borrador' : saving.value ? 'Guardando borrador…' : dirty.value ? 'Cambios pendientes de guardar' : draft.value ? `Borrador guardado · versión ${draft.value.revision}` : 'Completa el contexto para empezar');
const jump = async (next: CreationStep) => {
    if (actionBusy.value || (!props.trip && next !== 'contexto')) return;
    step.value = next; await nextTick(); stepHeading.value?.focus();
};
const autosave = (force = false): Promise<boolean> => saveQueue.run(async()=>{
    if (conflict.value) return false;
    clearTimeout(timer);
    saving.value = true; error.value = '';
    try {
        while ((!draft.value && force) || representation() !== saved.value || pending) {
            pending ??= captureDraftWrite(clientId.value,draft.value?.revision ?? 0,step.value,payload,crypto.randomUUID());
            const captured = JSON.stringify({step:pending.step,payload:pending.payload});
            const firstSave = pending.expected_revision === 0;
            const response = firstSave
                ? await axios.post<{draft:CreationDraft}>(route('import-drafts.store'),pending,{headers:{Accept:'application/json'}})
                : await axios.put<{draft:CreationDraft}>(route('import-drafts.update',pending.draft_id),pending,{headers:{Accept:'application/json'}});
            draft.value = response.data.draft; saved.value = captured; pending = null;
            if (firstSave && mounted) await new Promise<void>(resolve=>router.get(route('trips.create'),{draft:clientId.value},{only:['draft','drafts'],preserveState:true,preserveScroll:true,replace:true,onFinish:()=>resolve()}));
        }
        return true;
    } catch(failure) {
        const errors = requestErrors(failure,'draft','El borrador no se guardó en el servidor. Conservamos los campos en esta pantalla; reintenta al recuperar la conexión.');
        error.value = Object.values(errors).flat().join(' '); conflict.value = !!errors.conflict || !!errors.write_key;
        if (axios.isAxiosError(failure) && failure.response?.status === 422 && !conflict.value) pending = null;
        return false;
    } finally { saving.value = false; }
});
watch(()=>context.data(),data=>{ payload.context = creationContext(data); },{deep:true});
watch(()=>representation(),()=>{ if (!actionBusy.value && !conflict.value) { clearTimeout(timer); timer = setTimeout(()=>void autosave(),800); } });
onBeforeUnmount(()=>{mounted = false; clearTimeout(timer);});
watch(()=>props.draft,value=>{
    if (!value || value.id !== clientId.value) return;
    if (!draft.value?.trip_id && value.trip_id) {
        draft.value = value; step.value = value.step; Object.assign(payload,copyCreationPayload(value.payload)); Object.assign(context,creationContext(value.payload.context)); saved.value = representation(); pending = null;
    } else if (!dirty.value && !saving.value && value.revision >= (draft.value?.revision ?? 0)) draft.value = value;
});
watch(()=>props.draft?.id,id=>{
    if (id && id !== clientId.value) { clientId.value = id; draft.value = props.draft; Object.assign(payload,copyCreationPayload(props.draft!.payload)); Object.assign(context,creationContext(payload.context)); step.value = props.draft!.step; saved.value = representation(); pending = null; error.value = ''; conflict.value = false; }
});
const launch = async (mode: 'guided' | 'quick') => {
    if (actionBusy.value) return;
    if (!await autosave(true) || !draft.value) return;
    actionBusy.value = true; actionError.value = ''; context.clearErrors();
    router.post(route('import-drafts.launch',draft.value.id),{...context.data(),draft_revision:draft.value.revision,mode},{preserveScroll:true,
        onError:errors=>{ Object.assign(context.errors,validationErrors(errors)); actionError.value = Object.values(validationErrors(errors)).flat().join(' '); },
        onFinish:()=>{ actionBusy.value = false; if (Object.keys(context.errors).length) void nextTick(()=>document.querySelector<HTMLElement>('[aria-invalid="true"]')?.focus()); } });
};
const finish = async (mode: 'active' | 'close') => {
    if (hasEditor.value || actionBusy.value || !props.trip || !await autosave() || !draft.value) return;
    actionBusy.value = true; actionError.value = '';
    router.post(route('import-drafts.finish',draft.value.id),{draft_revision:draft.value.revision,expected_revision:props.trip.revision,mode},{
        onError:errors=>{ actionError.value = Object.values(validationErrors(errors)).flat().join(' '); },onFinish:()=>{ actionBusy.value = false; confirmation.value = null; } });
};
const askClose = () => { confirmation.value = {title:'Crear y cerrar importación',message:'Se conservará una revisión inmutable con tasas y costos, y se bloqueará la edición. El cierre no representa una venta. Podrás reabrirla y consultar este reporte después.',action:()=>{void finish('close');}}; };
const askDelete = (kind: 'products' | 'expenses', item: ProductData | ExpenseData) => {
    confirmation.value = {title:kind === 'products' ? 'Eliminar producto' : 'Eliminar gasto',message:'Se retirará de esta revisión y se recalcularán los costos. Sus valores anteriores se conservan en el historial.',action:()=>{
        if (!props.trip) return; actionBusy.value = true;
        axios.delete(route(`trips.${kind}.destroy`,[props.trip.id,item.id]),{data:{expected_revision:props.trip.revision},headers:{Accept:'application/json'}})
            .then(()=>router.reload()).catch(failure=>{actionError.value = Object.values(requestErrors(failure,'delete','No se pudo retirar el registro.')).flat().join(' ');})
            .finally(()=>{actionBusy.value = false; confirmation.value = null;});
    }};
};
const leave = async () => { if (await autosave()) router.visit(route('trips.index')); };
const savedVersion = () => router.visit(route('trips.create',{draft:clientId.value}));
const setProductDraft = (state: ProductEditorDraft | null) => { payload.product = state; };
const setExpenseDraft = (state: ExpenseEditorDraft | null) => { payload.expense = state; };
</script>
<template>
    <Head title="Nueva importación" />
    <FinancialLayout>
        <div class="f-heading"><div><div class="f-eyebrow">DEL CONTEXTO AL COSTO FINAL</div><h1>Nueva importación</h1><p>Una compra organizada, con cada conversión y gasto explicados.</p></div><div class="f-actions"><button class="f-button" :disabled="saving || actionBusy" @click="leave">Guardar y salir</button></div></div>
        <section v-if="!draft && drafts.data.length" class="f-panel"><div class="f-panel-heading"><h2>Altas que puedes retomar</h2><span class="f-hint">{{ drafts.total }} borradores guardados</span></div><div class="f-panel-body"><div v-for="item in drafts.data" :key="item.id" class="creation-resume"><div><strong>{{ item.name || 'Sin nombre todavía' }}</strong><p class="f-hint">{{ steps.find(s=>s.id === item.step)?.label }} · {{ new Date(item.updated_at).toLocaleString('es-CL') }}</p></div><Link :href="route('trips.create',{draft:item.id})" class="f-button">Retomar {{ item.name || 'borrador' }}</Link></div></div><div v-if="drafts.last_page > 1" class="f-pagination"><span>{{ drafts.current_page }} / {{ drafts.last_page }}</span><div class="f-actions"><Link v-if="drafts.prev_page_url" :href="drafts.prev_page_url" class="f-button">Anteriores</Link><Link v-if="drafts.next_page_url" :href="drafts.next_page_url" class="f-button">Siguientes</Link></div></div></section>
        <nav class="creation-steps" aria-label="Pasos para crear la importación"><button v-for="(item,index) in steps" :key="item.id" :aria-current="step === item.id ? 'step' : undefined" :disabled="actionBusy || (!trip && item.id !== 'contexto')" @click="jump(item.id)"><span class="creation-step-number">{{ index+1 }}</span><span><strong>{{ item.label }}</strong><small>{{ item.description }}</small></span></button></nav>
        <div class="creation-state"><p role="status" aria-live="polite">{{ status }}</p><span v-if="trip" class="f-hint">Importación #{{ trip.id }} · costeo revisión {{ trip.revision }}</span><button v-if="error && !conflict" class="f-button" @click="autosave()" :disabled="saving">Reintentar guardado</button></div>
        <div v-if="error || actionError" class="f-notice f-notice-error" role="alert">{{ error || actionError }}<button v-if="conflict" class="f-button" style="margin-top:10px" @click="savedVersion">Abrir versión guardada</button></div>
        <p v-if="draft && !props.draft" class="f-hint" style="margin-bottom:16px">El borrador ya está guardado en tu cuenta. Si sales, podrás retomarlo desde Nueva importación.</p>
        <h2 ref="stepHeading" tabindex="-1" class="creation-heading">{{ steps.find(s=>s.id === step)?.label }}</h2>
        <template v-if="step === 'contexto'">
            <section v-if="trip" class="f-panel"><div class="f-panel-body"><h3>Contexto guardado</h3><div class="f-breakdown-row"><span>Importación</span><strong>{{ trip.nombre }}</strong></div><div class="f-breakdown-row"><span>Empresa</span><strong>{{ trip.company?.nombre || 'Sin empresa' }}</strong></div><div class="f-breakdown-row"><span>Inicio</span><strong>{{ formatDateOnly(trip.fecha_inicio) }}</strong></div><div class="f-breakdown-row"><span>Referencia</span><strong>{{ formatRate(trip.tipo_cambio) }} CLP / {{ trip.moneda_trabajo }}</strong></div><p class="f-hint">Los cambios de contexto revalúan las operaciones estimadas y crean una nueva revisión.</p><div class="f-actions" style="margin-top:16px"><Link :href="route('trips.edit',trip.id)" class="f-button">Editar configuración guardada</Link><button class="f-button f-button-primary" @click="jump('productos')">Continuar con productos</button></div></div></section>
            <form v-else class="creation-context" @submit.prevent="launch('guided')" novalidate>
                <section class="f-panel"><div class="f-panel-heading"><h2>Identifica la compra</h2><span class="f-hint">Empresa y fechas opcionales</span></div><fieldset class="f-panel-body f-form-grid" :disabled="actionBusy"><div class="f-field" style="grid-column:1/-1"><label for="creation-name">Nombre de la importación *</label><input id="creation-name" class="f-input" v-model="context.nombre" maxlength="255" autocomplete="off" placeholder="Ej. Equipos de diagnóstico · octubre" :aria-invalid="!!context.errors.nombre" aria-describedby="creation-name-error" /><p id="creation-name-error" class="f-field-error">{{ context.errors.nombre }}</p></div><div class="f-field" style="grid-column:1/-1"><label for="creation-description">Descripción</label><textarea id="creation-description" class="f-input" v-model="context.descripcion" rows="3" maxlength="5000" placeholder="Referencia, proveedor o detalles para reconocer esta compra" :aria-invalid="!!context.errors.descripcion" aria-describedby="creation-description-error"></textarea><p id="creation-description-error" class="f-field-error">{{ context.errors.descripcion }}</p></div><div class="f-field" style="grid-column:1/-1"><label for="creation-company">Empresa</label><select id="creation-company" class="f-input" v-model="context.company_id" :aria-invalid="!!context.errors.company_id" aria-describedby="creation-company-hint"><option value="">Sin empresa asignada</option><option v-for="company in companies" :key="company.id" :value="company.id">{{ company.nombre }}</option></select><p id="creation-company-hint" class="f-hint">{{ context.errors.company_id || 'El contexto elegido se conservará al retomar el borrador.' }}</p></div><div class="f-field"><label for="creation-start">Fecha de inicio</label><input id="creation-start" class="f-input" v-model="context.fecha_inicio" type="date" :aria-invalid="!!context.errors.fecha_inicio" aria-describedby="creation-start-error" /><p id="creation-start-error" class="f-field-error">{{ context.errors.fecha_inicio }}</p></div><div class="f-field"><label for="creation-end">Fecha de fin</label><input id="creation-end" class="f-input" v-model="context.fecha_fin" type="date" :min="context.fecha_inicio" :aria-invalid="!!context.errors.fecha_fin" aria-describedby="creation-end-error" /><p id="creation-end-error" class="f-field-error">{{ context.errors.fecha_fin }}</p></div></fieldset></section>
                <section class="f-panel"><div class="f-panel-heading"><h2>Define la conversión a CLP</h2><span class="f-status">Moneda base · CLP</span></div><fieldset class="f-panel-body f-form-grid" :disabled="actionBusy"><div class="f-field"><label for="creation-currency">Moneda de trabajo *</label><select id="creation-currency" class="f-input" v-model="context.moneda_trabajo"><option value="USD">USD · dólar estadounidense</option><option value="EUR">EUR · euro</option><option value="CNY">CNY · yuan chino</option><option value="BRL">BRL · real brasileño</option><option value="PEN">PEN · sol peruano</option></select><p class="f-field-error">{{ context.errors.moneda_trabajo }}</p></div><div class="f-field"><label for="creation-rate">Tasa de referencia · CLP / {{ context.moneda_trabajo }} *</label><input id="creation-rate" class="f-input" v-model="context.tipo_cambio" type="text" inputmode="decimal" placeholder="Ej. 950" :aria-invalid="!!context.errors.tipo_cambio" aria-describedby="creation-rate-hint creation-rate-error" /><p id="creation-rate-hint" class="f-hint">Cuántos pesos vale 1 {{ context.moneda_trabajo }}. Usa coma para decimales, por ejemplo 950,5.</p><DecimalHint :value="context.tipo_cambio" :suffix="`pesos por 1 ${context.moneda_trabajo}`" :rate-currency="context.moneda_trabajo" /><p id="creation-rate-error" class="f-field-error">{{ context.errors.tipo_cambio }}</p></div><p class="f-hint" style="grid-column:1/-1">La tasa convierte una unidad de {{ context.moneda_trabajo }} a pesos chilenos. Los productos y gastos se registran en su moneda original y se calculan en CLP.</p></fieldset></section>
                <div class="f-actions"><button type="button" class="f-button" @click="launch('quick')" :disabled="actionBusy || saving">Alta rápida · abrir hoja</button><button type="submit" class="f-button f-button-primary" :disabled="actionBusy || saving">{{ actionBusy ? 'Guardando contexto…' : 'Continuar con productos' }}</button></div><p class="f-hint" style="margin-top:10px">Alta rápida guarda el mismo contexto y abre directamente la hoja para el operador frecuente.</p>
            </form>
        </template>
        <template v-if="trip">
            <div v-show="step === 'productos'"><ProductSheet :trip="trip" :latest-run="latestCostRun" embedded :draft-state="payload.product" @draft="setProductDraft" @delete="askDelete('products',$event)" /><div class="f-actions"><button class="f-button" @click="jump('contexto')">Volver al contexto</button><button class="f-button f-button-primary" @click="jump('gastos')">Continuar con gastos</button></div></div>
            <div v-show="step === 'gastos'"><ExpensePanel :trip="trip" :categories="categories" embedded :draft-state="payload.expense" @draft="setExpenseDraft" @delete="askDelete('expenses',$event)" /><p class="f-hint" style="margin-bottom:14px">Si no hay gastos adicionales, puedes continuar. El cierre verificará que el costo esté distribuido.</p><div class="f-actions"><button class="f-button" @click="jump('productos')">Volver a productos</button><button class="f-button f-button-primary" @click="jump('revision')">Revisar importación</button></div></div>
            <div v-if="step === 'revision' && estadisticas && projection"><div class="f-notice"><strong>{{ trip.nombre }}</strong><p>{{ trip.products.length }} productos · {{ trip.expenses.length }} gastos · costo guardado ${{ formatMoney(estadisticas.costo_total) }} CLP.</p></div><div v-if="hasEditor" class="f-notice f-notice-error" role="alert"><strong>Hay cambios pendientes en un editor.</strong><p>Guarda o cancela ese editor antes de terminar. Sus valores todavía no forman parte del costo guardado.</p><div class="f-actions" style="margin-top:8px"><button v-if="payload.product" class="f-button" @click="jump('productos')">Volver al producto pendiente</button><button v-if="payload.expense" class="f-button" @click="jump('gastos')">Volver al gasto pendiente</button></div></div><ReviewPanel :trip="trip" :stats="estadisticas" :projection="projection" :additional-blocks="hasEditor ? ['Guarda o cancela los editores pendientes.'] : []" @tab="jump('productos')" @close="!hasEditor && askClose()" /><div class="f-actions"><button class="f-button" @click="jump('gastos')">Volver a gastos</button><button class="f-button f-button-primary" @click="finish('active')" :disabled="hasEditor || actionBusy || saving">Terminar y mantener en curso</button></div><p class="f-hint" style="margin-top:12px">Puedes mantenerla en curso para completar precios, documentos o nuevos costos. Cerrar conserva el costeo y bloquea la edición.</p></div>
        </template>
        <Modal :show="!!confirmation" :title="confirmation?.title" @close="!actionBusy && (confirmation = null)"><p>{{ confirmation?.message }}</p><template #footer><div class="f-actions"><button class="f-button" :disabled="actionBusy" @click="confirmation = null">Cancelar</button><button class="f-button f-button-primary" :disabled="actionBusy" @click="confirmation?.action()">{{ actionBusy ? 'Guardando…' : 'Confirmar' }}</button></div></template></Modal>
    </FinancialLayout>
</template>
<style scoped>
.creation-steps{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:8px;margin:0 0 18px}.creation-steps button{display:flex;align-items:center;gap:10px;text-align:left;border:1px solid var(--f-line);border-radius:7px;background:var(--f-surface);padding:14px 12px;min-width:0;overflow-wrap:anywhere}.creation-steps button[aria-current=step]{border-color:var(--f-brand);background:var(--f-brand-soft)}.creation-step-number{border:1px solid var(--f-line);border-radius:50%;width:26px;height:26px;display:inline-flex;align-items:center;justify-content:center;flex-shrink:0;font-size:12px;color:var(--f-brand)}.creation-steps strong{display:block;font-size:12px}.creation-steps small{display:block;font-size:10px;color:var(--f-muted);margin-top:3px}.creation-steps button:disabled{opacity:.55;cursor:not-allowed}.creation-heading{font-size:18px;font-weight:650;margin:18px 0 12px}.creation-state{display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px;font-size:11px;color:var(--f-muted);margin:16px 0}.creation-resume{display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;padding:10px 0;border-bottom:1px solid var(--f-line);overflow-wrap:anywhere}.creation-context{max-width:940px}.creation-resume strong{font-size:13px}.creation-resume:last-child{border:0}@media(max-width:1000px){.creation-steps{grid-template-columns:repeat(2,minmax(0,1fr))}}@media(max-width:760px){.creation-steps button{padding:10px 8px;gap:8px}.creation-steps small{display:none}}
</style>
