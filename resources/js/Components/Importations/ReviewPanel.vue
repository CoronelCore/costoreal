<script setup lang="ts">
import { computed, ref, watch, onBeforeUnmount } from 'vue';
import axios from 'axios';
import { Link, router } from '@inertiajs/vue3';
import { useRoute } from '@/Composables/useRoute';
import { requestErrors } from '@/Utils/validation';
import type { ImportReview, ImportDetail, FinancialStatistics, SalesProjection } from '@/Types/financial';
import { formatMoney } from '@/Utils/format';
const props = defineProps<{ trip: ImportDetail; stats: FinancialStatistics; projection: SalesProjection; additionalBlocks?: string[] }>();
const emit = defineEmits<{ close: [event: MouseEvent]; tab: [id: string] }>();
const route = useRoute();
const review = ref<ImportReview | null>(null);
const loading = ref(false);
const error = ref('');
const conflict = ref(false);
let controller: AbortController | undefined;
let sequence = 0;
const load = async () => {
    controller?.abort(); const captured = ++sequence; controller = new AbortController(); loading.value = true; error.value = ''; conflict.value = false; review.value = null;
    try { const response = await axios.get<ImportReview>(route('trips.review',props.trip.id),{params:{expected_revision:props.trip.revision},signal:controller.signal,headers:{Accept:'application/json'}}); if (sequence === captured) review.value = response.data; }
    catch(failure) { if (sequence === captured && !axios.isCancel(failure)) { const errors = requestErrors(failure,'review','No se pudo verificar el cierre. Reintenta antes de continuar.'); error.value = Object.values(errors).flat().join(' '); conflict.value = !!errors.conflict; } }
    finally { if (sequence === captured) loading.value = false; }
};
watch(()=>[props.trip.id,props.trip.revision],()=>void load(),{immediate:true});
onBeforeUnmount(()=>{sequence++;controller?.abort();});
const blocks = computed(()=>[...(props.additionalBlocks ?? []),...(review.value?.blocks ?? [])]);
const canClose = computed(()=>!!review.value?.can_close && !loading.value && !error.value && !blocks.value.length);
</script>
<template>
    <section class="f-panel"><div class="f-panel-heading"><h2>Revisión antes del cierre</h2><span class="f-status" :class="{ 'f-status-warning': blocks.length }">{{ loading ? 'Verificando…' : error ? 'Revisión pendiente' : blocks.length ? `${blocks.length} ${blocks.length === 1 ? 'bloqueo' : 'bloqueos'}` : review ? 'Costo conciliado' : 'Revisión pendiente' }}</span></div><div class="f-panel-body"><div class="f-breakdown-row"><span>Productos registrados</span><strong>{{ trip.products.length }} líneas · {{ projection.units }} unidades</strong></div><div class="f-breakdown-row"><span>Compra convertida</span><strong>${{ formatMoney(stats.total_productos_clp) }}</strong></div><div class="f-breakdown-row"><span>Gastos registrados</span><strong>${{ formatMoney(stats.total_gastos_clp) }}</strong></div><div class="f-breakdown-row"><span>Sin distribuir</span><strong :class="{ 'f-negative': Number(stats.gastos_sin_asignar_clp) !== 0 }">${{ formatMoney(stats.gastos_sin_asignar_clp) }}</strong></div><div class="f-breakdown-row"><span>Tasa de referencia</span><strong>{{ trip.tipo_cambio }} CLP / {{ trip.moneda_trabajo }}</strong></div><div class="f-breakdown-row"><span>Costo a conservar</span><strong>${{ formatMoney(stats.costo_total) }}</strong></div><div v-if="blocks.length" class="f-notice f-notice-error" style="margin-top:18px"><p v-for="block in blocks" :key="block">{{ block }}</p></div><p v-if="loading" role="status" class="f-hint" style="margin-top:18px">Verificando tasas, importes guardados y reparto…</p><div v-if="error" class="f-notice f-notice-error" role="alert" style="margin-top:18px">{{ error }}<button v-if="conflict" class="f-button" @click="router.reload()" style="margin-top:10px">Recargar datos guardados</button><button v-else class="f-button" @click="load" :disabled="loading" style="margin-top:10px">Reintentar revisión</button></div><div v-if="review?.differences_count" style="margin-top:12px"><Link :href="`${route('trips.show',trip.id)}#historial`" class="f-button">Comparar y conciliar en Historial</Link></div><div v-if="review?.warnings.length" class="f-notice" style="margin-top:18px"><strong>Advertencias que permiten cerrar</strong><p v-for="warning in review.warnings" :key="warning">{{ warning }}</p></div><p class="f-hint" style="margin-top:18px">Al cerrar se conserva una revisión inmutable con tasas, entradas y reparto. Reabrir permite continuar trabajando sin modificar esa revisión.</p><div v-if="trip.estado === 'activo'" class="f-actions" style="margin-top:20px"><button class="f-button" @click="emit('tab','productos')">Revisar hoja</button><button class="f-button f-button-primary" :disabled="!canClose" @click="emit('close', $event)">Cerrar importación</button></div><p v-else class="f-hint" style="margin-top:18px">La importación está cerrada. Consulta sus revisiones y reportes en Historial.</p></div></section>
</template>
