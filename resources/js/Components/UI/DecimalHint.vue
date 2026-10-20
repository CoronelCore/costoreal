<script setup lang="ts">
import { computed } from 'vue';
import { rateWarning, readDecimal } from '@/Utils/format';
/** Shows how a typed number is read ("Se lee: 1.050,5 pesos por 1 USD") and warns about unlikely rates. */
const props = defineProps<{ value: string | number | null | undefined; suffix: string; rateCurrency?: string }>();
const reading = computed(() => readDecimal(props.value));
const warning = computed(() => props.rateCurrency ? rateWarning(props.rateCurrency, props.value) : '');
</script>
<template>
    <p v-if="reading" class="f-hint" aria-live="polite">Se lee: <strong>{{ reading }}</strong> {{ suffix }}</p>
    <p v-if="warning" class="f-field-error" role="status">{{ warning }}</p>
</template>
