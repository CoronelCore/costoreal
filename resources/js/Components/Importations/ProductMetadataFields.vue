<script setup>
const props = defineProps({ form:Object, prefix:String, referenceRate:String, workingCurrency:String });
const setMode = event => {
    props.form.rate_mode = event.target.value;
    if (props.form.rate_mode === 'paid' && ['trip-reference','legacy-unverified'].includes(props.form.rate_source)) props.form.rate_source = '';
};
</script>
<template>
    <div class="f-form-grid">
        <div class="f-field"><label :for="`${prefix}-mode`">Conversión de la compra</label><select :id="`${prefix}-mode`" class="f-input" :value="form.rate_mode" @change="setMode"><option value="estimated">Estimado: tasa de referencia</option><option value="paid">Pagado: tasa histórica</option></select><p class="f-hint">{{ form.rate_mode === 'paid' ? 'La tasa histórica se conserva al revaluar. Corregir el pago requiere revisar sus datos originales.' : `Referencia: ${form.moneda === 'CLP' ? '1' : referenceRate} CLP / ${form.moneda}.` }}</p><span class="f-field-error">{{ form.errors.rate_mode }}</span></div>
        <template v-if="form.rate_mode === 'paid'"><div class="f-field"><label :for="`${prefix}-rate`">Tasa histórica · CLP / {{ form.moneda }}</label><input :id="`${prefix}-rate`" class="f-input" type="number" :value="form.applied_rate" @input="form.applied_rate = $event.target.value" min="0.00000001" max="1000000" step="0.00000001" required /><p v-if="form.moneda === 'CLP'" class="f-hint">Una compra en CLP utiliza tasa 1.</p><span class="f-field-error">{{ form.errors.applied_rate }}</span></div><div class="f-field"><label :for="`${prefix}-source`">Fuente de la tasa del pago</label><input :id="`${prefix}-source`" class="f-input" v-model="form.rate_source" maxlength="255" required placeholder="Comprobante bancario, factura…" /><span class="f-field-error">{{ form.errors.rate_source }}</span></div><div class="f-field"><label :for="`${prefix}-date`">Fecha del pago de la compra</label><input :id="`${prefix}-date`" class="f-input" type="date" v-model="form.rate_date" required /><span class="f-field-error">{{ form.errors.rate_date }}</span></div></template>
        <div class="f-field"><label :for="`${prefix}-weight`">Peso total de la línea · kg</label><input :id="`${prefix}-weight`" class="f-input" type="number" :value="form.weight" @input="form.weight = $event.target.value" min="0" max="999999999" step="0.00000001" placeholder="Sin dato" /><span class="f-field-error">{{ form.errors.weight }}</span><p class="f-hint">Peso de todas las unidades de esta línea.</p></div>
        <div class="f-field"><label :for="`${prefix}-volume`">Volumen total de la línea · m³</label><input :id="`${prefix}-volume`" class="f-input" type="number" :value="form.volume" @input="form.volume = $event.target.value" min="0" max="999999999" step="0.00000001" placeholder="Sin dato" /><span class="f-field-error">{{ form.errors.volume }}</span><p class="f-hint">Volumen de todas las unidades de esta línea.</p></div>
    </div>
</template>
