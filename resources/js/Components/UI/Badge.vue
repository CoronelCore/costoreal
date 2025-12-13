<script setup>
import { computed } from 'vue';

const props = defineProps({
    variant: {
        type: String,
        default: 'gray',
        validator: (value) => ['success', 'warning', 'danger', 'info', 'gray'].includes(value)
    }
});

const badgeClasses = computed(() => {
    return `badge badge-${props.variant}`;
});
</script>

<template>
    <span :class="badgeClasses">
        <slot />
    </span>
</template>
