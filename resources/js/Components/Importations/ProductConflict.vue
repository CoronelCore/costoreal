<script setup>
import { computed } from 'vue';
const props = defineProps({ comparison:Array, choices:Object, revision:Number, editable:Boolean, busy:Boolean });
const emit = defineEmits(['resolve','refresh']);
const labels = {nombre:'Nombre',moneda:'Moneda',cantidad:'Cantidad',costo_unitario:'Costo unitario original',weight:'Peso total · kg',volume:'Volumen total · m³',rate_mode:'Conversión',applied_rate:'Tasa histórica',rate_source:'Fuente de la tasa',rate_date:'Fecha de pago'};
const unresolved = computed(()=>props.comparison.some(row=>row.conflict && !['draft','current'].includes(props.choices[row.field])));
const value = data => data === '' || data === null || data === undefined ? 'Sin dato' : ({estimated:'Estimado',paid:'Pagado'})[data] || String(data);
</script>
<template>
    <section class="f-draft product-conflict" aria-label="Comparación para resolver conflicto"><h3>Tu borrador sigue disponible</h3><p class="f-hint" style="margin:8px 0">La importación está en revisión {{ revision }}. Compara los valores de origen, tu borrador y la versión actual. Los campos que no editaste tomarán su valor actual.</p>
        <p v-if="!editable" class="f-notice f-notice-error">La importación está cerrada. Conserva o descarta el borrador; no se guardará sobre el cierre.</p>
        <article v-for="row in comparison" :key="row.field" class="conflict-field"><strong>{{ labels[row.field] }}</strong><span v-if="row.conflict" class="f-status f-status-warning" style="margin-left:8px">Elegir valor</span><div class="conflict-values"><div><span class="f-hint">Origen</span><p>{{ value(row.base) }}</p></div><div><span class="f-hint">Tu borrador</span><p>{{ value(row.draft) }}</p></div><div><span class="f-hint">Actual</span><p>{{ value(row.current) }}</p></div></div><fieldset v-if="row.conflict"><legend class="sr-only">Resolver {{ labels[row.field] }}</legend><label><input type="radio" :name="`conflict-${row.field}`" value="draft" v-model="choices[row.field]" /> Conservar mi borrador</label><label><input type="radio" :name="`conflict-${row.field}`" value="current" v-model="choices[row.field]" /> Usar valor actual</label></fieldset></article>
        <p v-if="!comparison.length" class="f-hint">Este producto no cambió. Otra operación de la importación generó una nueva revisión.</p>
        <div class="f-actions" style="margin-top:16px"><button type="button" class="f-button" @click="emit('refresh')" :disabled="busy">Actualizar comparación</button><button type="button" class="f-button f-button-primary" @click="emit('resolve')" :disabled="busy || unresolved || !editable">Preparar borrador con revisión actual</button></div><p class="f-hint" style="margin-top:10px">Preparar no guarda. Revisa el borrador resultante y pulsa Guardar.</p>
    </section>
</template>
<style scoped>
.product-conflict h3{font-weight:650}.conflict-field{padding:14px 0;border-top:1px solid var(--f-line);margin-top:12px;overflow-wrap:anywhere}.conflict-values{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px;margin-top:8px;font-size:12px}.conflict-values p{margin-top:4px}.conflict-field fieldset{display:flex;flex-wrap:wrap;gap:8px 20px;margin-top:8px}.conflict-field label{display:flex;align-items:center;gap:8px;min-height:44px;font-size:12px}@media(max-width:600px){.conflict-values{grid-template-columns:minmax(0,1fr)}}
</style>
