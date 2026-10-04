<script setup lang="ts">
import { ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { formatMoney } from '@/Utils/format';
import PriceGoal from './PriceGoal.vue';
import type { ImportDetail, SalesProjection, PricingBatch, ProductData, RevisionMutation } from '@/Types/financial';
import { useRoute } from '@/Composables/useRoute';
import { inputValue } from '@/Utils/input';
const props = defineProps<{ trip: ImportDetail; projection: SalesProjection; lastBatch: PricingBatch | null }>();
const route = useRoute();
const editingId = ref<number | null>(null);
const form = useForm<RevisionMutation & { precio_venta: string }>({ precio_venta: '', expected_revision: props.trip.revision });
const edit = (product: ProductData) => { editingId.value = product.id; form.clearErrors(); form.precio_venta = product.precio_venta ?? ''; form.expected_revision = props.trip.revision; };
const save = (product: ProductData) => form.transform(data => ({ ...data, precio_venta: data.precio_venta === '' ? null : data.precio_venta })).patch(route('trips.products.update-precio-venta', [props.trip.id, product.id]), { preserveScroll: true, onSuccess: () => editingId.value = null });
</script>
<template>
    <PriceGoal :trip="trip" :last-batch="lastBatch" />
    <div class="f-notice">Precios de venta por unidad en CLP. {{ projection.priced_lines }} / {{ projection.lines }} líneas con precio. Los cambios se reflejan en la proyección después de guardar.</div>
    <section class="f-panel"><div class="f-panel-heading"><h2>Precios y cobertura</h2><span class="f-hint">Utilidad proyectada; ventas aún no registradas</span></div><div v-if="trip.products.length" class="f-panel-body"><article v-for="product in trip.products" :key="product.id" class="f-breakdown-row" style="align-items:center;flex-wrap:wrap"><div><strong style="display:block;text-align:left">{{ product.nombre }}</strong><span class="f-hint">Costo unitario: ${{ formatMoney(product.costo_final_unitario_clp,2) }} · {{ product.cantidad }} unidades</span></div><form v-if="editingId === product.id" class="f-actions" @submit.prevent="save(product)"><label class="sr-only" :for="`price-${product.id}`">Precio de {{ product.nombre }}</label><input :id="`price-${product.id}`" class="f-input" type="number" style="width:140px" :value="form.precio_venta" @input="form.precio_venta = inputValue($event)" min="0.01" step="0.01" placeholder="Sin precio" /><button class="f-button f-button-primary" type="submit" :disabled="form.processing">Guardar</button><button class="f-button" type="button" @click="editingId = null" :disabled="form.processing">Cancelar</button><span class="f-field-error">{{ form.errors.precio_venta }}</span></form><div v-else class="f-actions"><strong>{{ product.precio_venta === null ? 'Pendiente' : '$' + formatMoney(product.precio_venta,2) }}</strong><button v-if="trip.estado === 'activo'" class="f-button" @click="edit(product)">{{ product.precio_venta === null ? 'Asignar precio' : 'Editar precio' }}</button></div></article></div><div v-else class="f-empty"><h2>Agrega productos primero</h2><p>Después podrás fijar precios y evaluar la proyección de esta compra.</p></div></section>
</template>
