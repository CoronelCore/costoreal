<script setup>
import { computed } from 'vue';
import {
    BuildingOfficeIcon,
    TruckIcon,
    ShoppingCartIcon,
    GlobeAltIcon,
    CubeIcon,
    SparklesIcon,
    RocketLaunchIcon,
    BriefcaseIcon,
    HomeModernIcon,
    BeakerIcon,
    Cog6ToothIcon,
    LightBulbIcon,
} from '@heroicons/vue/24/outline';

const props = defineProps({
    icon: {
        type: String,
        default: 'BuildingOfficeIcon'
    },
    color: {
        type: String,
        default: '#6366f1'
    },
    size: {
        type: String,
        default: 'md',
        validator: (value) => ['sm', 'md', 'lg', 'xl'].includes(value)
    }
});

// Map icon names to actual icon components
const iconMap = {
    BuildingOfficeIcon,
    TruckIcon,
    ShoppingCartIcon,
    GlobeAltIcon,
    CubeIcon,
    SparklesIcon,
    RocketLaunchIcon,
    BriefcaseIcon,
    HomeModernIcon,
    BeakerIcon,
    Cog6ToothIcon,
    LightBulbIcon,
};

const iconComponent = computed(() => {
    return iconMap[props.icon] || BuildingOfficeIcon;
});

const sizeClasses = computed(() => {
    return {
        'sm': 'h-4 w-4',
        'md': 'h-6 w-6',
        'lg': 'h-8 w-8',
        'xl': 'h-12 w-12',
    }[props.size];
});
</script>

<template>
    <component
        :is="iconComponent"
        :class="sizeClasses"
        :style="{ color: color }"
    />
</template>
