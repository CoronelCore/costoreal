<script setup lang="ts">
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import axios from 'axios';
import Modal from '@/Components/UI/Modal.vue';
import { formatMoney } from '@/Utils/format';

import { useRoute } from '@/Composables/useRoute';
import { inputValue } from '@/Utils/input';
import { pricingRequest } from '@/Utils/advancedDrafts';
import { requestErrors, validationErrors } from '@/Utils/validation';
import type { ImportDetail, PricingBatch, RevisionMutation } from '@/Types/financial';
import type { PricingSettings, PricingPreview, ErrorBag } from '@/Types/advanced';
const props = defineProps<{ trip: ImportDetail; lastBatch: PricingBatch | null }>();
const route = useRoute();
const selected = ref(props.trip.products.map(product => product.id));
const policy = ref<PricingSettings>({ method: 'margin', target: '20', commission: '0', quantum: 1 });
const preview = ref<PricingPreview | null>(null);
const pending = ref(false);
const errors = ref<ErrorBag>({});
const confirming = ref(false);
const applyForm = useForm<RevisionMutation>({expected_revision:props.trip.revision});
const applyErrors = computed(()=>validationErrors(applyForm.errors));
let controller: AbortController | undefined;
let requestSequence = 0;
const selectedCount = computed(() => selected.value.length);
const messages = computed(() => [...Object.values(errors.value).flat(), ...Object.values(applyErrors.value).flat()]);
const invalidated = (clearErrors = true) => {
    requestSequence++;
    controller?.abort();
    pending.value = false;
    preview.value = null;
    confirming.value = false;
    if (clearErrors) { errors.value = {}; applyForm.clearErrors(); }
};
watch([policy, selected], () => invalidated(), { deep: true });
watch(() => props.trip.revision, () => invalidated(false));
watch(() => props.trip.products.map(product => product.id), ids => {
    selected.value = selected.value.filter(id => ids.includes(id));
});
onBeforeUnmount(() => controller?.abort());
const generate = async () => {
    invalidated();
    const sequence = requestSequence;
    pending.value = true;
    controller = new AbortController();
    try {
        const response = await axios.post<PricingPreview>(route('trips.pricing.preview', props.trip.id), pricingRequest(policy.value,selected.value,props.trip.revision), { signal: controller.signal, headers: { Accept: 'application/json' } });
        if (sequence === requestSequence) preview.value = response.data;
    } catch (error) {
        if (sequence === requestSequence && !axios.isCancel(error)) {
            errors.value = requestErrors(error,'pricing','No se pudo generar la vista previa. Revisa tu conexión y vuelve a intentarlo.');
        }
    } finally {
        if (sequence === requestSequence) pending.value = false;
    }
};
const apply = () => {
    const result = preview.value;
    if (!result || applyForm.processing) return;
    applyForm.transform(() => ({ ...result.policy, expected_revision: result.revision }))
        .post(route('trips.pricing.apply', props.trip.id), {
            preserveScroll: true,
            onSuccess: () => { preview.value = null; confirming.value = false; },
            onError: () => { confirming.value = false; },
        });
};
</script>

<template>
    <section v-if="trip.estado === 'activo' && trip.products.length" class="f-panel">
        <div class="f-panel-heading">
            <div><h2>Precio por objetivo</h2><p class="f-hint">Calcula, compara y aplica a las líneas elegidas.</p></div>
            <span class="f-badge">{{ selectedCount }} seleccionadas</span>
        </div>
        <div class="f-panel-body">
            <p class="f-hint" style="margin-bottom:16px">Margen es utilidad / venta. Recargo es utilidad / costo. La comisión opcional se calcula sobre la venta bruta; el resumen de la importación muestra utilidad antes de comisiones de venta.</p>
            <form @submit.prevent="generate">
                <fieldset :disabled="pending || applyForm.processing">
                    <legend class="sr-only">Objetivo comercial y productos</legend>
                    <div class="f-form-grid">
                        <div class="f-field"><label for="price-method">Tipo de objetivo</label><select id="price-method" class="f-input" v-model="policy.method"><option value="margin">Margen sobre venta</option><option value="markup">Recargo sobre costo</option></select></div>
                        <div class="f-field"><label for="price-goal">{{ policy.method === 'margin' ? 'Margen objetivo' : 'Recargo objetivo' }} · %</label><input id="price-goal" class="f-input" type="number" :value="policy.target" @input="policy.target = inputValue($event)" min="0" :max="policy.method === 'margin' ? '99.99' : '1000'" step="0.01" required /></div>
                        <div class="f-field"><label for="price-commission">Comisión de venta · %</label><input id="price-commission" class="f-input" type="number" :value="policy.commission" @input="policy.commission = inputValue($event)" min="0" max="99.99" step="0.01" required /></div>
                        <div class="f-field"><label for="price-quantum">Redondear hacia arriba</label><select id="price-quantum" class="f-input" v-model="policy.quantum"><option :value="1">Al peso</option><option :value="10">A 10 CLP</option><option :value="100">A 100 CLP</option><option :value="1000">A 1.000 CLP</option></select></div>
                    </div>
                    <div class="f-actions" style="margin:16px 0 8px"><button class="f-button" type="button" @click="selected = trip.products.map(product => product.id)">Seleccionar todas</button><button class="f-button" type="button" @click="selected = []">Limpiar selección</button></div>
                    <div style="max-height:240px;overflow:auto">
                        <label v-for="product in trip.products" :key="product.id" class="f-price-selection">
                            <input type="checkbox" :value="product.id" v-model="selected" />
                            <span>{{ product.nombre }}<small class="f-hint" style="display:block">{{ product.cantidad }} unidades · costo total ${{ formatMoney(product.costo_final_total_clp) }}</small></span>
                        </label>
                    </div>
                    <div class="f-actions" style="margin-top:16px"><button class="f-button f-button-primary" type="submit" :disabled="!selectedCount">{{ pending ? 'Calculando…' : 'Generar vista previa' }}</button><span class="f-hint">No cambia los precios guardados.</span></div>
                </fieldset>
            </form>
            <div v-if="messages.length" class="f-notice f-notice-error" role="alert" style="margin-top:16px"><p v-for="(message,index) in messages" :key="index">{{ message }}</p><button v-if="errors.conflict || applyErrors.conflict" class="f-button" @click="router.reload()">Recargar revisión</button></div>
            <template v-if="preview">
                <div class="f-panel-heading" style="padding:20px 0 12px"><h3>Vista previa · revisión {{ preview.revision }}</h3><span class="f-hint">Precios por unidad en CLP</span></div>
                <div class="f-table-scroll f-desktop-table"><table class="f-table"><caption class="sr-only">Comparación de precios actuales y propuestos</caption><thead><tr><th>Producto</th><th class="f-numeric">Actual</th><th class="f-numeric">Propuesto</th><th class="f-numeric">Margen tras comisión</th></tr></thead><tbody><tr v-for="line in preview.lines" :key="line.id"><td>{{ line.name }}</td><td class="f-numeric">{{ line.previous_price === null ? 'Pendiente' : '$' + formatMoney(line.previous_price,2) }}</td><td class="f-numeric"><strong>${{ formatMoney(line.price) }}</strong></td><td class="f-numeric">{{ formatMoney(line.margin_after_commission,2) }} %</td></tr></tbody></table></div>
                <div class="f-mobile-cards"><article v-for="line in preview.lines" :key="line.id" class="f-mobile-card"><strong>{{ line.name }}</strong><div class="f-mobile-card-bottom"><span class="f-hint">Actual → propuesto</span><strong>{{ line.previous_price === null ? 'Pendiente' : '$' + formatMoney(line.previous_price,2) }} → ${{ formatMoney(line.price) }}</strong></div><p class="f-hint" style="margin-top:8px">Margen tras comisión: {{ formatMoney(line.margin_after_commission,2) }} %</p></article></div>
                <div class="f-breakdown-row"><span>Venta proyectada · selección</span><strong>${{ formatMoney(preview.totals.revenue) }}</strong></div>
                <div class="f-breakdown-row"><span>Comisión estimada · selección</span><strong>${{ formatMoney(preview.totals.commission,2) }}</strong></div>
                <div class="f-breakdown-row"><span>Utilidad tras comisión · selección</span><strong>${{ formatMoney(preview.totals.profit_after_commission,2) }}</strong></div>
                <div class="f-actions" style="margin-top:16px"><button class="f-button f-button-primary" @click="confirming = true">Revisar aplicación de {{ preview.lines.length }} precios</button><button class="f-button" @click="preview = null">Descartar vista previa</button></div>
            </template>
        </div>
    </section>
    <div v-if="lastBatch" class="f-notice">Último objetivo aplicado: {{ lastBatch.policy.method === 'margin' ? 'margen' : 'recargo' }} {{ formatMoney(lastBatch.policy.target,2) }} % · comisión {{ formatMoney(lastBatch.policy.commission,2) }} % · {{ lastBatch.lines_count }} líneas · revisión {{ lastBatch.revision }}. Los precios posteriores pueden haber cambiado.</div>
    <Modal :show="confirming" :closeable="!applyForm.processing" title="Aplicar precios por objetivo" @close="!applyForm.processing && (confirming = false)">
        <p v-if="preview">Se reemplazarán los precios de {{ preview.lines.length }} líneas de la revisión {{ preview.revision }}. Los productos sin seleccionar conservarán su precio. El historial registrará el objetivo y la comisión usados.</p>
        <div class="f-actions" style="margin-top:20px"><button class="f-button" @click="confirming = false" :disabled="applyForm.processing">Cancelar</button><button class="f-button f-button-primary" @click="apply" :disabled="applyForm.processing">{{ applyForm.processing ? 'Aplicando…' : 'Aplicar precios' }}</button></div>
    </Modal>
</template>

<style scoped>
.f-price-selection { display:flex;align-items:center;gap:12px;min-height:48px;padding:8px 0;cursor:pointer; }
.f-price-selection input { flex:0 0 auto;width:18px;height:18px; }
.f-price-selection span { min-width:0;overflow-wrap:anywhere; }
</style>
