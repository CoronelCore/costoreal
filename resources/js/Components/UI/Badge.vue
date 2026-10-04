<script setup>
import { computed } from 'vue';

const props = defineProps({
    variant: {
        type: String,
        default: 'gray',
        validator: (value) => ['primary', 'success', 'warning', 'danger', 'info', 'gray'].includes(value)
    }
});

const variants = { primary: 'badge-primary', success: 'badge-success', warning: 'badge-warning', danger: 'badge-danger', info: 'badge-info', gray: 'badge-gray' };
const badgeClasses = computed(() => `badge ${variants[props.variant] || variants.gray}`);
</script>

<template>
    <span :class="badgeClasses">
        <slot />
    </span>
</template>
