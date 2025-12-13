<script setup>
import { computed } from 'vue';
import {
    TruckIcon,
    HomeIcon,
    CakeIcon,
    PaperAirplaneIcon,
    MapPinIcon,
    CurrencyDollarIcon,
    BoltIcon,
    EllipsisHorizontalIcon,
} from '@heroicons/vue/24/outline';

const props = defineProps({
    icon: {
        type: String,
        default: 'dots-horizontal'
    },
    color: {
        type: String,
        default: '#6B7280'
    },
    size: {
        type: String,
        default: 'md',
        validator: (value) => ['sm', 'md', 'lg'].includes(value)
    }
});

// Map icon names to actual icon components
const iconMap = {
    'truck': TruckIcon,
    'home': HomeIcon,
    'cake': CakeIcon,
    'paper-airplane': PaperAirplaneIcon,
    'location-marker': MapPinIcon,
    'cash': CurrencyDollarIcon,
    'lightning-bolt': BoltIcon,
    'dots-horizontal': EllipsisHorizontalIcon,
};

const iconComponent = computed(() => {
    return iconMap[props.icon] || EllipsisHorizontalIcon;
});

const sizeClasses = computed(() => {
    return {
        'sm': 'h-4 w-4',
        'md': 'h-5 w-5',
        'lg': 'h-6 w-6',
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
