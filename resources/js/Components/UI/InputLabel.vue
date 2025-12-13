<script setup>
defineProps({
    value: {
        type: String,
        default: undefined
    },
    required: {
        type: Boolean,
        default: false
    },
    for: {
        type: String,
        default: undefined
    }
});
</script>

<template>
    <label :for="for" class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2">
        <span v-if="value">{{ value }}</span>
        <slot v-else />
        <span v-if="required" class="text-danger-500 dark:text-danger-400 ml-1">*</span>
    </label>
</template>
