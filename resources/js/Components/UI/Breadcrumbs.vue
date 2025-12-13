<script setup>
import { Link } from '@inertiajs/vue3';
import { HomeIcon, ChevronRightIcon } from '@heroicons/vue/24/outline';

const props = defineProps({
    items: {
        type: Array,
        required: true,
        validator: (value) => {
            return value.every(item =>
                item.label &&
                (item.href !== undefined) &&
                (item.current !== undefined)
            );
        }
    }
});
</script>

<template>
    <nav class="flex items-center gap-2 text-sm" aria-label="Breadcrumb">
        <!-- Home -->
        <Link
            href="/"
            class="text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200 transition-colors"
            aria-label="Home"
        >
            <HomeIcon class="w-5 h-5" />
        </Link>

        <!-- Breadcrumb Items -->
        <template v-for="(item, index) in items" :key="index">
            <ChevronRightIcon class="w-4 h-4 text-gray-400 dark:text-gray-600" />

            <!-- Mobile: Hide middle items -->
            <template v-if="index === 0 || index === items.length - 1 || items.length <= 2">
                <Link
                    v-if="!item.current && item.href"
                    :href="item.href"
                    class="text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200 transition-colors"
                >
                    {{ item.label }}
                </Link>

                <span
                    v-else
                    class="font-semibold text-gray-900 dark:text-white"
                    aria-current="page"
                >
                    {{ item.label }}
                </span>
            </template>

            <!-- Mobile: Show ellipsis for hidden middle items -->
            <span
                v-else-if="index === 1 && items.length > 2"
                class="text-gray-400 dark:text-gray-600 hidden sm:inline"
            >
                ...
            </span>
        </template>
    </nav>
</template>

<style scoped>
/* Show all items on desktop */
@media (min-width: 640px) {
    nav :deep(a),
    nav :deep(span) {
        display: inline !important;
    }
}

/* Hide middle items on mobile */
@media (max-width: 639px) {
    nav {
        overflow-x: auto;
        scrollbar-width: none;
        -ms-overflow-style: none;
    }

    nav::-webkit-scrollbar {
        display: none;
    }
}
</style>
