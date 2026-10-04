<script setup>
import { computed, nextTick, watch } from 'vue';
import { TransitionRoot, TransitionChild, Dialog, DialogPanel, DialogTitle } from '@headlessui/vue';
import { XMarkIcon } from '@heroicons/vue/24/outline';

const props = defineProps({
    show: {
        type: Boolean,
        default: false
    },
    maxWidth: {
        type: String,
        default: '2xl',
        validator: (value) => ['sm', 'md', 'lg', 'xl', '2xl', '3xl', '4xl'].includes(value)
    },
    closeable: {
        type: Boolean,
        default: true
    },
    title: {
        type: String,
        default: undefined
    }
});

const emit = defineEmits(['close', 'closed']);
let returnFocus = null;
watch(() => props.show, (visible) => {
    if (visible) {
        const active = document.activeElement;
        returnFocus = active instanceof HTMLElement && active !== document.body ? active : null;
    }
}, { flush: 'sync', immediate: true });

const finishClose = async () => {
    await nextTick();
    if (props.show) return;
    if (returnFocus?.isConnected) returnFocus.focus({ preventScroll: true });
    returnFocus = null;
    emit('closed');
};

const close = () => {
    if (props.closeable) {
        emit('close');
    }
};

const maxWidthClass = computed(() => {
    return {
        'sm': 'sm:max-w-sm',
        'md': 'sm:max-w-md',
        'lg': 'sm:max-w-lg',
        'xl': 'sm:max-w-xl',
        '2xl': 'sm:max-w-2xl',
        '3xl': 'sm:max-w-3xl',
        '4xl': 'sm:max-w-4xl',
    }[props.maxWidth];
});
</script>

<template>
    <TransitionRoot :show="show" as="template" @after-leave="finishClose">
        <Dialog as="div" class="financial-overlay relative z-50" @close="close">
            <!-- Backdrop -->
            <TransitionChild
                as="template"
                enter="ease-out duration-300"
                enter-from="opacity-0"
                enter-to="opacity-100"
                leave="ease-in duration-200"
                leave-from="opacity-100"
                leave-to="opacity-0"
            >
                <div class="modal-backdrop" />
            </TransitionChild>

            <!-- Modal Container -->
            <div class="fixed inset-0 z-50 overflow-y-auto">
                <div class="flex min-h-full items-end justify-center sm:items-center p-0 sm:p-4">
                    <TransitionChild
                        as="template"
                        enter="ease-out duration-300"
                        enter-from="opacity-0 translate-y-full sm:translate-y-0 sm:scale-95"
                        enter-to="opacity-100 translate-y-0 sm:scale-100"
                        leave="ease-in duration-200"
                        leave-from="opacity-100 translate-y-0 sm:scale-100"
                        leave-to="opacity-0 translate-y-full sm:translate-y-0 sm:scale-95"
                    >
                        <DialogPanel
                            :class="maxWidthClass"
                            class="modal-content animate-scale-in w-full text-left transition-all sm:my-8 fixed bottom-0 left-0 right-0 rounded-t-3xl sm:relative sm:rounded-2xl border border-gray-200 dark:border-slate-700"
                        >
                            <!-- Header -->
                            <div v-if="title || closeable" class="card-header flex items-center justify-between">
                                <DialogTitle v-if="title" as="h3" class="text-lg font-semibold text-gray-900 dark:text-white">
                                    {{ title }}
                                </DialogTitle>
                                <DialogTitle v-else as="h3" class="text-lg font-semibold text-gray-900 dark:text-white"><slot name="header"><slot name="title">Confirmar acción</slot></slot></DialogTitle>

                                <button
                                    v-if="closeable"
                                    type="button"
                                    aria-label="Cerrar diálogo"
                                    class="rounded-lg p-1 text-gray-400 hover:text-gray-600 hover:bg-gray-100 dark:hover:bg-slate-700 dark:hover:text-gray-200 transition-colors"
                                    @click="close"
                                >
                                    <XMarkIcon class="h-5 w-5" />
                                </button>
                            </div>

                            <!-- Body -->
                            <div class="card-body">
                                <slot />
                            </div>

                            <!-- Footer -->
                            <div v-if="$slots.footer" class="card-footer">
                                <slot name="footer" />
                            </div>
                        </DialogPanel>
                    </TransitionChild>
                </div>
            </div>
        </Dialog>
    </TransitionRoot>
</template>
