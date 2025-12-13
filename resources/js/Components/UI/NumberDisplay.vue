<script setup>
import { computed } from 'vue';

const props = defineProps({
    value: {
        type: Number,
        required: true
    },
    currency: {
        type: Boolean,
        default: true
    },
    prefix: {
        type: String,
        default: ''
    },
    suffix: {
        type: String,
        default: ''
    },
    size: {
        type: String,
        default: 'md',
        validator: (value) => ['sm', 'md', 'lg'].includes(value)
    },
    variant: {
        type: String,
        default: 'default',
        validator: (value) => ['default', 'gradient'].includes(value)
    }
});

const formattedValue = computed(() => {
    const formatted = props.value.toLocaleString('es-CL');

    let result = '';

    // Add prefix
    if (props.prefix) {
        result += props.prefix;
    }

    // Add currency symbol
    if (props.currency) {
        result += '$';
    }

    // Add formatted number
    result += formatted;

    // Add suffix
    if (props.suffix) {
        result += props.suffix;
    }

    return result;
});

const sizeClasses = computed(() => {
    const sizes = {
        sm: 'text-lg',
        md: 'text-2xl',
        lg: 'text-4xl'
    };
    return sizes[props.size];
});

const variantClasses = computed(() => {
    return props.variant === 'gradient' ? 'text-gradient-primary' : 'text-gray-900 dark:text-white';
});

const combinedClasses = computed(() => {
    return `financial-number ${sizeClasses.value} ${variantClasses.value}`;
});
</script>

<template>
    <span :class="combinedClasses">
        {{ formattedValue }}
    </span>
</template>
