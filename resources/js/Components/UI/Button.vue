<script setup>
import { computed } from 'vue';

const props = defineProps({
    variant: {
        type: String,
        default: 'primary',
        validator: (value) => ['primary', 'secondary', 'success', 'danger', 'warning', 'info', 'ghost', 'link'].includes(value)
    },
    size: {
        type: String,
        default: 'md',
        validator: (value) => ['xs', 'sm', 'md', 'lg', 'xl'].includes(value)
    },
    type: {
        type: String,
        default: 'button'
    },
    block: {
        type: Boolean,
        default: false
    },
    disabled: {
        type: Boolean,
        default: false
    },
    loading: {
        type: Boolean,
        default: false
    },
    icon: {
        type: [Object, Function, String],
        default: null
    }
});

const buttonClasses = computed(() => {
    const classes = ['btn'];
    
    // Variant
    classes.push(`btn-${props.variant}`);

    // Size
    if (props.size !== 'md') {
        classes.push(`btn-${props.size}`);
    }

    // Block
    if (props.block) {
        classes.push('w-full');
    }

    return classes.join(' ');
});
</script>

<template>
    <button
        :type="type"
        :class="buttonClasses"
        :disabled="disabled || loading"
    >
        <!-- Loading spinner -->
        <svg
            v-if="loading"
            class="animate-spin h-5 w-5 mr-2"
            xmlns="http://www.w3.org/2000/svg"
            fill="none"
            viewBox="0 0 24 24"
        >
            <circle
                class="opacity-25"
                cx="12"
                cy="12"
                r="10"
                stroke="currentColor"
                stroke-width="4"
            />
            <path
                class="opacity-75"
                fill="currentColor"
                d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
            />
        </svg>

        <!-- Slot content -->
        <slot />
    </button>
</template>
