<script setup>
import { inject, ref } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import { formatMoney } from '@/Utils/format';
import Modal from '@/Components/UI/Modal.vue';
const props = defineProps({ trip: Object, trashed: Array });
const route = inject('route');
const showTrash = ref(false);
const selected = ref(null);
const deleting = ref(null);
const busy = ref(false);
const form = useForm({ file:null, expected_revision:props.trip.revision });
const upload = () => form.transform(data=>({...data,expected_revision:props.trip.revision})).post(route('trips.documents.store',props.trip.id), {forceFormData:true,preserveScroll:true,onSuccess:()=>form.reset()});
const remove = () => { busy.value=true; router.delete(route('trips.documents.destroy',[props.trip.id,deleting.value.id]),{data:{expected_revision:props.trip.revision},preserveScroll:true,onFinish:()=>{busy.value=false;deleting.value=null;}}); };
const restore = document => router.post(route('trips.documents.restore',[props.trip.id,document.id]),{expected_revision:props.trip.revision},{preserveScroll:true});
const kind = {receipt:'Comprobante de gasto',product_image:'Imagen de producto',other:'Documento'};
</script>
<template>
    <section class="f-panel"><div class="f-panel-heading"><div><h2>Documentos privados</h2><p class="f-hint">Solo disponibles para el propietario de esta importación</p></div><button class="f-button" :aria-pressed="showTrash" @click="showTrash = !showTrash">{{ showTrash ? 'Ver documentos' : `Papelera (${trashed?.length || 0})` }}</button></div>
        <form v-if="trip.estado === 'activo' && !showTrash" class="f-panel-body f-filters" @submit.prevent="upload"><div class="f-field f-search-field"><label for="document-upload">Adjuntar documento</label><input id="document-upload" class="f-input" type="file" accept="application/pdf,image/jpeg,image/png,image/webp" @change="form.file = $event.target.files[0]" required :key="trip.revision" /><span class="f-hint">PDF, JPEG, PNG o WebP · hasta 10 MB</span><span class="f-field-error">{{ form.errors.file }}</span></div><button class="f-button f-button-primary" type="submit" :disabled="form.processing || !form.file">{{ form.processing ? 'Guardando…' : 'Guardar documento' }}</button></form>
        <div class="f-panel-body"><article v-for="document in showTrash ? trashed : trip.documents" :key="document.id" class="f-breakdown-row" style="flex-wrap:wrap;align-items:center"><div style="min-width:0"><strong class="f-table-title">{{ document.filename }}</strong><p class="f-hint">{{ kind[document.kind] }} · {{ formatMoney(String(Math.ceil(document.size / 1024))) }} KB</p></div><div class="f-actions"><template v-if="showTrash"><button v-if="trip.estado === 'activo'" class="f-button" @click="restore(document)">Restaurar</button></template><template v-else><button v-if="document.mime.startsWith('image/')" class="f-button" @click="selected = document">Vista previa</button><a class="f-button" :href="`${document.download_url}?download=1`">Descargar</a><button v-if="trip.estado === 'activo'" class="f-button f-button-danger" @click="deleting = document">A papelera</button></template></div></article><div v-if="!(showTrash ? trashed : trip.documents)?.length" class="f-empty"><h2>{{ showTrash ? 'La papelera está vacía' : 'Documentos en un solo lugar' }}</h2><p>{{ showTrash ? 'Los documentos eliminados estarán disponibles aquí para restaurarlos.' : 'Adjunta facturas o comprobantes y consúltalos junto a los costos de esta compra.' }}</p></div></div>
    </section>
    <Modal :show="!!selected" :title="selected?.filename" @close="selected = null"><img v-if="selected" :src="selected.download_url" :alt="`Vista previa de ${selected.filename}`" style="max-width:100%;max-height:70vh;object-fit:contain;margin:auto" /></Modal>
    <Modal :show="!!deleting" title="Mover documento a la papelera" @close="!busy && (deleting = null)"><p class="text-gray-700 dark:text-slate-200">«{{ deleting?.filename }}» dejará de estar disponible para descargar. Puedes restaurarlo desde la papelera.</p><template #footer><div class="f-actions"><button class="f-button" :disabled="busy" @click="deleting = null">Cancelar</button><button class="f-button f-button-primary" :disabled="busy" @click="remove">{{ busy ? 'Guardando…' : 'Mover a papelera' }}</button></div></template></Modal>
</template>
