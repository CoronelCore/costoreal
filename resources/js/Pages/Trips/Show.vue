<script setup lang="ts">
import { computed, ref, nextTick, watch } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import FinancialLayout from '@/Layouts/FinancialLayout.vue';
import ImportSummary from '@/Components/Importations/ImportSummary.vue';
import ProductSheet from '@/Components/Importations/ProductSheet.vue';
import ExpensePanel from '@/Components/Importations/ExpensePanel.vue';
import PricePanel from '@/Components/Importations/PricePanel.vue';
import ReviewPanel from '@/Components/Importations/ReviewPanel.vue';
import DocumentPanel from '@/Components/Importations/DocumentPanel.vue';
import HistoryPanel from '@/Components/Importations/HistoryPanel.vue';
import ScenariosPanel from '@/Components/Importations/ScenariosPanel.vue';
import Modal from '@/Components/UI/Modal.vue';
import { useRoute } from '@/Composables/useRoute';
import type { ImportShowProps } from '@/Types/financial';
import { useTabs } from '@/Composables/useTabs';
import { formatDateOnly } from '@/Utils/format';
import { ArrowLeftIcon, ArrowDownTrayIcon, CheckIcon, ArrowPathIcon, PencilIcon } from '@heroicons/vue/24/outline';
const route = useRoute();
const props = defineProps<ImportShowProps>();
const { activeTab, setTab } = useTabs(`trip-${props.trip.id}-tab`, 'resumen', ['resumen','productos','gastos','precio-venta','revision','historial','documentos','escenarios','config'],props.initialTab ?? undefined);
const tabs = computed(() => [{id:'resumen',label:'Resumen'}, {id:'productos',label:'Hoja de productos',count:props.trip.products.length}, {id:'gastos',label:'Gastos',count:props.trip.expenses.length}, {id:'precio-venta',label:'Precios'}, {id:'documentos',label:'Documentos',count:props.trip.documents?.length || 0}, {id:'escenarios',label:'Escenarios'}, {id:'revision',label:'Revisión'}, {id:'historial',label:'Historial'}]);
const confirmation = ref<{ title: string; message: string; kind?: 'reopen'; action: () => void } | null>(null);
const visibleConfirmation = ref<typeof confirmation.value>(null);
watch(confirmation, value => { if (value) visibleConfirmation.value = value; }, { flush: 'sync' });
const cancelConfirmationButton = ref<HTMLButtonElement | null>(null);
const focusOpener = (event?: Event) => {
    if (event?.currentTarget instanceof HTMLElement) event.currentTarget.focus({ preventScroll: true });
};
const busy = ref(false);
const reopenReasonInput = ref<HTMLTextAreaElement | null>(null);
const reopenForm = useForm({reason:'',expected_revision:props.trip.revision});
const askClose = (event?: Event) => { focusOpener(event); confirmation.value = { title:'Cerrar importación', message:'Se conservará esta revisión con sus tasas y costos, y se bloqueará su edición. Puedes reabrirla después; el cierre original seguirá disponible.', action: () => router.patch(route('trips.close',props.trip.id), {expected_revision:props.trip.revision}, {preserveScroll:true,onFinish:finish}) }; };
const askReopen = (event?: Event) => { focusOpener(event); reopenForm.reset(); reopenForm.clearErrors(); reopenForm.expected_revision = props.trip.revision; confirmation.value = { kind:'reopen',title:'Reabrir importación', message:'Se abrirá una nueva revisión para continuar trabajando. El cierre anterior y sus reportes permanecerán en el historial.', action: () => reopenForm.patch(route('trips.reopen',props.trip.id), {preserveScroll:true,onSuccess:finish,onFinish:()=>{busy.value = false; if (Object.keys(reopenForm.errors).length) void nextTick(()=>reopenReasonInput.value?.focus());}}) }; };
const askTrash = (event?: Event) => { focusOpener(event); confirmation.value = { title:'Mover importación a la papelera', message:`«${props.trip.nombre}» saldrá del Control y la Analítica. Sus costos, documentos y revisiones se conservarán y podrás restaurarla desde la papelera.`, action: () => router.delete(route('trips.destroy',props.trip.id), {data:{expected_revision:props.trip.revision},onFinish:finish}) }; };
const askDelete = (type: 'products' | 'expenses', item: { id: number; nombre?: string; concepto?: string }) => { confirmation.value = { title:type === 'products' ? 'Eliminar producto' : 'Eliminar gasto', message:`Se quitará «${item.nombre || item.concepto}» de esta revisión y se recalculará el reparto. Sus datos anteriores permanecen en el historial.`, action: () => router.delete(route(`trips.${type}.destroy`,[props.trip.id,item.id]), {data:{expected_revision:props.trip.revision},preserveScroll:true,onFinish:finish}) }; };
const finish = () => { busy.value = false; confirmation.value = null; };
const confirm = () => { busy.value = true; confirmation.value?.action(); };
</script>
<template>
    <Head :title="trip.nombre" />
    <FinancialLayout>
        <Link :href="route('trips.index')" class="f-hint" style="display:inline-flex;align-items:center;gap:6px;margin-bottom:20px"><ArrowLeftIcon style="width:14px;height:14px" /> Control de importaciones</Link>
        <div class="f-heading"><div style="min-width:0"><div class="f-eyebrow">IMPORTACIÓN #{{ trip.id }} · REVISIÓN {{ trip.revision }}</div><h1>{{ trip.nombre }}</h1><p>{{ trip.company?.nombre || 'Sin empresa asignada' }} · {{ formatDateOnly(trip.fecha_inicio) }} <span class="f-status" :class="{ 'f-status-closed': trip.estado !== 'activo' }" style="margin-left:8px">{{ trip.estado === 'activo' ? 'En curso' : 'Cerrada' }}</span></p></div><div class="f-actions"><a :href="route('trips.export.pdf',trip.id)" class="f-button"><ArrowDownTrayIcon /> PDF</a><a :href="route('trips.export.excel',trip.id)" class="f-button">Excel</a><Link v-if="trip.estado === 'activo'" :href="route('trips.edit',trip.id)" class="f-button"><PencilIcon /> Configurar</Link><button v-if="trip.estado === 'activo'" class="f-button f-button-primary" @click="setTab('revision')"><CheckIcon /> Revisar cierre</button><button v-else class="f-button" @click="askReopen"><ArrowPathIcon /> Reabrir</button></div></div>
        <nav class="f-tabs" aria-label="Secciones de la importación"><button v-for="tab in tabs" :key="tab.id" :class="{active:activeTab === tab.id}" :aria-current="activeTab === tab.id ? 'page' : undefined" @click="setTab(tab.id)">{{ tab.label }} <span v-if="tab.count !== undefined" class="f-hint">{{ tab.count }}</span></button></nav>
        <div class="f-actions" style="justify-content:flex-end;margin-bottom:16px"><button class="f-button" @click="askTrash" :disabled="busy">Mover a la papelera</button></div>
        <ImportSummary v-if="activeTab === 'resumen'" :trip="trip" :stats="estadisticas" :projection="projection" @tab="setTab" />
        <ProductSheet v-else-if="activeTab === 'productos'" :trip="trip" :latest-run="latestCostRun" @delete="item => askDelete('products',item)" />
        <ExpensePanel v-else-if="activeTab === 'gastos'" :trip="trip" :categories="categories" @delete="item => askDelete('expenses',item)" />
        <DocumentPanel v-else-if="activeTab === 'documentos'" :trip="trip" :trashed="trashedDocuments" />
        <PricePanel v-else-if="activeTab === 'precio-venta'" :trip="trip" :projection="projection" :last-batch="lastPricingBatch" />
        <ReviewPanel v-else-if="activeTab === 'revision'" :trip="trip" :stats="estadisticas" :projection="projection" @close="askClose" @tab="setTab" />
        <ScenariosPanel v-else-if="activeTab === 'escenarios'" :trip="trip" :scenarios="scenarios" />
        <HistoryPanel v-else-if="activeTab === 'historial'" :trip="trip" :history="history" :baseline="legacyBaseline" />
        <section v-else class="f-panel"><div class="f-panel-body"><h2>Configuración de la importación</h2><p class="f-hint">Moneda original {{ trip.moneda_trabajo }} · tasa de referencia {{ trip.tipo_cambio }} CLP / {{ trip.moneda_trabajo }}</p><Link v-if="trip.estado === 'activo'" :href="route('trips.edit',trip.id)" class="f-button" style="margin-top:16px">Editar configuración</Link></div></section>
        <Modal :show="!!confirmation" :title="visibleConfirmation?.title" :initial-focus="(visibleConfirmation?.kind === 'reopen' ? reopenReasonInput : cancelConfirmationButton) ?? undefined" :closeable="!busy" @closed="visibleConfirmation = null" @close="!busy && (confirmation = null)"><p class="text-gray-700 dark:text-slate-200">{{ visibleConfirmation?.message }}</p><div v-if="visibleConfirmation?.kind === 'reopen'" class="f-field" style="margin-top:18px"><label for="reopen-reason">Motivo de la reapertura *</label><textarea ref="reopenReasonInput" id="reopen-reason" class="f-input" v-model="reopenForm.reason" rows="4" maxlength="1000" :disabled="busy" :aria-invalid="!!reopenForm.errors.reason" aria-describedby="reopen-reason-help reopen-reason-error" placeholder="Ej. Incorporar el flete definitivo o corregir una cantidad"></textarea><p id="reopen-reason-help" class="f-hint">Se conservará junto a tu nombre y la nueva revisión.</p><p id="reopen-reason-error" class="f-field-error" role="alert">{{ reopenForm.errors.reason }}</p><p v-for="(message,key) in reopenForm.errors" v-show="key !== 'reason'" :key="key" class="f-field-error" role="alert">{{ message }}</p></div><template #footer><div class="f-actions"><button ref="cancelConfirmationButton" class="f-button" @click="confirmation = null" :disabled="busy || !confirmation">Cancelar</button><button class="f-button f-button-primary" @click="confirm" :disabled="busy || !confirmation">{{ busy ? 'Guardando…' : 'Confirmar' }}</button></div></template></Modal>
    </FinancialLayout>
</template>
