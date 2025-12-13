<script setup>
import { computed } from 'vue';
import { CheckCircleIcon, ExclamationTriangleIcon, XCircleIcon, InformationCircleIcon, XMarkIcon } from '@heroicons/vue/24/outline';

const props = defineProps({
    type: {
        type: String,
        default: 'info',
        validator: (value) => ['success', 'error', 'warning', 'info'].includes(value)
    },
    dismissible: {
        type: Boolean,
        default: false
    },
    title: {
        type: String,
        default: undefined
    }
});

const emit = defineEmits(['dismiss']);

const alertClasses = computed(() => {
    // Map 'error' to 'danger' to match CSS classes
    const typeMap = {
        'error': 'danger',
        'success': 'success',
        'warning': 'warning',
        'info': 'info'
    };
    return `alert alert-${typeMap[props.type] || props.type}`;
});

const icon = computed(() => {
    return {
        success: CheckCircleIcon,
        error: XCircleIcon,
        warning: ExclamationTriangleIcon,
        info: InformationCircleIcon,
    }[props.type];
});

const dismiss = () => {
    emit('dismiss');
};
</script>

<template>
    <div :class="alertClasses">
        <component :is="icon" class="h-5 w-5 flex-shrink-0" />

        <div class="flex-1">
            <p v-if="title" class="font-semibold mb-1">{{ title }}</p>
            <div class="text-sm">
                <slot />
            </div>
        </div>

        <button
            v-if="dismissible"
            type="button"
            class="flex-shrink-0 rounded-lg p-1 hover:bg-black/5 dark:hover:bg-white/5 transition-colors"
            @click="dismiss"
        >
            <XMarkIcon class="h-4 w-4" />
        </button>
    </div>
</template>
