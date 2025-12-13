<script setup>
import { computed } from 'vue';
import { TransitionGroup } from 'vue';
import { useToast } from '@/Composables/useToast';
import {
    CheckCircleIcon,
    ExclamationCircleIcon,
    ExclamationTriangleIcon,
    InformationCircleIcon,
    XMarkIcon
} from '@heroicons/vue/24/outline';

const { toasts, remove } = useToast();

const getIcon = (type) => {
    const icons = {
        success: CheckCircleIcon,
        error: ExclamationCircleIcon,
        warning: ExclamationTriangleIcon,
        info: InformationCircleIcon,
    };
    return icons[type] || InformationCircleIcon;
};

const getColorClasses = (type) => {
    const colors = {
        success: 'bg-green-50 dark:bg-green-900/20 border-green-200 dark:border-green-800 text-green-800 dark:text-green-200',
        error: 'bg-red-50 dark:bg-red-900/20 border-red-200 dark:border-red-800 text-red-800 dark:text-red-200',
        warning: 'bg-yellow-50 dark:bg-yellow-900/20 border-yellow-200 dark:border-yellow-800 text-yellow-800 dark:text-yellow-200',
        info: 'bg-blue-50 dark:bg-blue-900/20 border-blue-200 dark:border-blue-800 text-blue-800 dark:text-blue-200',
    };
    return colors[type] || colors.info;
};

const getIconColorClasses = (type) => {
    const colors = {
        success: 'text-green-500 dark:text-green-400',
        error: 'text-red-500 dark:text-red-400',
        warning: 'text-yellow-500 dark:text-yellow-400',
        info: 'text-blue-500 dark:text-blue-400',
    };
    return colors[type] || colors.info;
};
</script>

<template>
    <div
        aria-live="assertive"
        class="pointer-events-none fixed bottom-4 right-4 z-50 flex flex-col items-end space-y-4"
    >
        <TransitionGroup
            enter-active-class="toast-enter-active"
            leave-active-class="toast-leave-active"
        >
            <div
                v-for="toast in toasts"
                :key="toast.id"
                :class="getColorClasses(toast.type)"
                class="pointer-events-auto w-full max-w-sm overflow-hidden rounded-xl shadow-xl border-2 p-4"
            >
                <div class="flex items-start gap-3">
                    <!-- Icon -->
                    <div class="flex-shrink-0">
                        <component
                            :is="getIcon(toast.type)"
                            :class="getIconColorClasses(toast.type)"
                            class="h-6 w-6"
                        />
                    </div>

                    <!-- Message -->
                    <div class="flex-1 pt-0.5">
                        <p class="text-sm font-medium">
                            {{ toast.message }}
                        </p>
                    </div>

                    <!-- Close Button -->
                    <div class="flex-shrink-0">
                        <button
                            type="button"
                            class="inline-flex rounded-lg p-1.5 hover:bg-black/5 dark:hover:bg-white/5 focus:outline-none focus:ring-2 focus:ring-offset-2 transition-colors"
                            @click="remove(toast.id)"
                        >
                            <span class="sr-only">Dismiss</span>
                            <XMarkIcon class="h-5 w-5" />
                        </button>
                    </div>
                </div>
            </div>
        </TransitionGroup>
    </div>
</template>
