<script setup lang="ts">
import type { ImportDetail } from '@/Types/financial';
import { formatMoney, formatPesos } from '@/Utils/format';
defineProps<{ trip: ImportDetail }>();
const emit = defineEmits<{ tab: [id: string] }>();
const signed = (value: string) => (value.startsWith('-') ? '-$' : '$') + formatPesos(value.replace('-', ''));
const tone =(value: string | null) => value === null ? undefined : { 'f-negative': Number(value) < 0, 'f-positive': Number(value) > 0 };
</script>
<template>
    <section v-if="trip.products.length" class="f-panel" aria-labelledby="product-results-title">
        <div class="f-panel-heading"><div><h2 id="product-results-title">Resultado por producto</h2><p class="f-hint">Por unidad, en pesos chilenos</p></div><button v-if="trip.products.some(p => p.resultado.precio_venta === null)" class="f-button" @click="emit('tab', 'precio-venta')">Poner precios</button></div>
        <div class="f-table-scroll f-desktop-table"><table class="f-table"><caption class="sr-only">Lo pagado, el costo real, el precio y la ganancia de cada producto, por unidad</caption>
            <thead><tr><th>Producto</th><th class="f-numeric">Unidades</th><th class="f-numeric">Pagado por unidad</th><th class="f-numeric">+ Gastos por unidad</th><th class="f-numeric">Costo real por unidad</th><th class="f-numeric">Precio de venta</th><th class="f-numeric">Ganancia por unidad</th><th class="f-numeric">Margen</th></tr></thead>
            <tbody><tr v-for="product in trip.products" :key="product.id"><td><strong>{{ product.nombre }}</strong></td><td class="f-numeric">{{ product.cantidad }}</td><td class="f-numeric">${{ formatPesos(product.resultado.compra_unitaria_clp) }}</td><td class="f-numeric">${{ formatPesos(product.resultado.gastos_unitarios_clp) }}</td><td class="f-numeric"><strong>${{ formatPesos(product.resultado.costo_unitario_clp) }}</strong></td><td class="f-numeric">{{ product.resultado.precio_venta === null ? 'Sin precio' : '$' + formatPesos(product.resultado.precio_venta) }}</td><td class="f-numeric"><strong :class="tone(product.resultado.ganancia_unitaria_clp)">{{ product.resultado.ganancia_unitaria_clp === null ? '—' : signed(product.resultado.ganancia_unitaria_clp) }}</strong></td><td class="f-numeric">{{ product.resultado.margen === null ? '—' : formatMoney(product.resultado.margen, 1) + '%' }}</td></tr></tbody>
        </table></div>
        <div class="f-mobile-cards"><article v-for="product in trip.products" :key="product.id" class="f-mobile-card"><div class="f-mobile-card-top"><div><strong class="f-table-title">{{ product.nombre }}</strong><span class="f-table-subtitle">{{ product.cantidad }} unidades</span></div></div>
            <div class="f-mobile-card-bottom"><span class="f-hint">Pagado por unidad</span><span>${{ formatPesos(product.resultado.compra_unitaria_clp) }}</span></div>
            <div class="f-mobile-card-bottom" style="margin-top:8px"><span class="f-hint">Costo real por unidad</span><strong>${{ formatPesos(product.resultado.costo_unitario_clp) }}</strong></div>
            <div class="f-mobile-card-bottom" style="margin-top:8px"><span class="f-hint">Precio de venta</span><span>{{ product.resultado.precio_venta === null ? 'Sin precio' : '$' + formatPesos(product.resultado.precio_venta) }}</span></div>
            <div v-if="product.resultado.ganancia_unitaria_clp !== null" class="f-mobile-card-bottom" style="margin-top:8px"><span class="f-hint">Ganancia por unidad</span><strong :class="tone(product.resultado.ganancia_unitaria_clp)">{{ signed(product.resultado.ganancia_unitaria_clp) }} ·{{ formatMoney(product.resultado.margen, 1) }}%</strong></div>
        </article></div>
        <p class="f-panel-body f-hint">Pagado: lo que costó cada unidad en el origen, convertido a pesos. Costo real: lo pagado más su parte de los gastos. La ganancia es una proyección si vendes a ese precio.</p>
    </section>
</template>
