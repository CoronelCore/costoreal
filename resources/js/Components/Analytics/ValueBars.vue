<script setup lang="ts">
import { computed } from 'vue';
import { barWidths } from '@/Utils/analytics';
import { analyticsMoney } from '@/Utils/format';
const props = defineProps<{ items: { key: string | number; label: string; value: string }[] }>();
const widths = computed(() => barWidths(props.items.map(item => item.value)));
</script>
<template>
    <div class="analytics-bars" aria-hidden="true">
        <div v-for="(item, index) in items" :key="item.key" class="analytics-bar-row">
            <div class="analytics-bar-label"><span>{{ item.label }}</span><strong>${{ analyticsMoney(item.value) }}</strong></div>
            <div class="analytics-bar-track"><span :style="{ width: `${widths[index]}%` }" :class="{ negative: item.value.startsWith('-') }"></span></div>
        </div>
    </div>
</template>
<style scoped>
.analytics-bars{padding:20px;display:grid;gap:15px}.analytics-bar-label{display:flex;justify-content:space-between;gap:14px;font-size:11px;margin-bottom:5px}.analytics-bar-label span{overflow-wrap:anywhere;min-width:0}.analytics-bar-label strong{flex-shrink:0;font-weight:550}.analytics-bar-track{height:5px;background:var(--f-line);border-radius:3px;overflow:hidden}.analytics-bar-track span{display:block;height:100%;background:var(--f-brand);border-radius:3px}.analytics-bar-track .negative{background:var(--f-danger)}
</style>
