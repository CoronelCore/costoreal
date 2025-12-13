<script setup>
import { computed } from 'vue';
import { Menu, MenuButton, MenuItems, MenuItem } from '@headlessui/vue';

const props = defineProps({
    align: {
        type: String,
        default: 'right',
        validator: (value) => ['left', 'right'].includes(value)
    },
    width: {
        type: String,
        default: '48',
        validator: (value) => ['48', '56', '64'].includes(value)
    },
    contentClasses: {
        type: String,
        default: 'py-1 bg-white dark:bg-slate-800'
    }
});

const widthClass = computed(() => {
    return {
        '48': 'w-48',
        '56': 'w-56',
        '64': 'w-64',
    }[props.width];
});

const alignmentClasses = computed(() => {
    if (props.align === 'left') {
        return 'origin-top-left left-0';
    }

    return 'origin-top-right right-0';
});
</script>

<template>
    <Menu as="div" class="relative inline-block text-left">
        <MenuButton as="template">
            <slot name="trigger" />
        </MenuButton>

        <Transition
            enter-active-class="transition ease-out duration-200 transform"
            enter-from-class="opacity-0 scale-95"
            enter-to-class="opacity-100 scale-100"
            leave-active-class="transition ease-in duration-150 transform"
            leave-from-class="opacity-100 scale-100"
            leave-to-class="opacity-0 scale-95"
        >
            <MenuItems
                :class="[widthClass, alignmentClasses]"
                class="absolute z-50 mt-2 rounded-xl shadow-xl border border-gray-200 dark:border-slate-700 focus:outline-none overflow-hidden"
            >
                <div :class="contentClasses" class="rounded-xl">
                    <slot />
                </div>
            </MenuItems>
        </Transition>
    </Menu>
</template>

<style scoped>
/* Custom MenuItem wrapper */
:deep(.dropdown-item) {
    @apply block w-full px-4 py-2.5 text-sm text-left text-gray-700 dark:text-slate-300;
    @apply hover:bg-gray-100 dark:hover:bg-slate-700;
    @apply transition-colors duration-150 cursor-pointer;
    @apply focus:bg-gray-100 dark:focus:bg-slate-700 focus:outline-none;
}

:deep(.dropdown-item:first-child) {
    @apply rounded-t-xl;
}

:deep(.dropdown-item:last-child) {
    @apply rounded-b-xl;
}

:deep(.dropdown-divider) {
    @apply border-t border-gray-200 dark:border-slate-700 my-1;
}

/* Active state */
:deep(.dropdown-item:active) {
    @apply bg-gray-200 dark:bg-slate-600;
}
</style>
