<script setup>
import { computed } from 'vue';
import { ArrowUpIcon, ArrowDownIcon } from '@heroicons/vue/24/solid';

const props = defineProps({
    title: {
        type: String,
        required: true
    },
    value: {
        type: [String, Number],
        required: true
    },
    icon: {
        type: Object,
        required: true
    },
    trend: {
        type: Object,
        default: null,
        validator: (value) => {
            if (!value) return true;
            return value.direction && ['up', 'down'].includes(value.direction) &&
                   typeof value.value === 'number' &&
                   typeof value.label === 'string';
        }
    },
    variant: {
        type: String,
        default: 'primary',
        validator: (value) => ['primary', 'success', 'warning', 'danger'].includes(value)
    }
});

const gradientClasses = computed(() => {
    const gradients = {
        primary: 'bg-gradient-primary',
        success: 'bg-gradient-success',
        warning: 'bg-linear-to-br from-warning-400 to-warning-600',
        danger: 'bg-gradient-danger'
    };
    return gradients[props.variant];
});

const textGradientClasses = computed(() => {
    const textGradients = {
        primary: 'text-gradient-primary',
        success: 'text-gradient-success',
        warning: 'bg-linear-to-br from-warning-500 to-warning-700 bg-clip-text text-transparent',
        danger: 'bg-linear-to-br from-danger-500 to-danger-700 bg-clip-text text-transparent'
    };
    return textGradients[props.variant];
});

const trendBadgeClasses = computed(() => {
    if (!props.trend) return '';

    const isPositive = props.trend.direction === 'up';
    const badges = {
        primary: isPositive ? 'badge-success' : 'badge-danger',
        success: isPositive ? 'badge-success' : 'badge-danger',
        warning: isPositive ? 'badge-success' : 'badge-danger',
        danger: isPositive ? 'badge-success' : 'badge-danger'
    };

    return `badge ${badges[props.variant]}`;
});

const TrendIcon = computed(() => {
    if (!props.trend) return null;
    return props.trend.direction === 'up' ? ArrowUpIcon : ArrowDownIcon;
});
</script>

<template>
    <div class="card card-hover group">
        <div class="card-body">
            <!-- Icon Circle -->
            <div class="flex items-start justify-between mb-4">
                <div :class="[gradientClasses, 'p-3 rounded-2xl shadow-lg group-hover:scale-110 transition-transform duration-300']">
                    <component :is="icon" class="w-6 h-6 text-white" />
                </div>

                <!-- Trend Badge -->
                <div v-if="trend" :class="trendBadgeClasses">
                    <component :is="TrendIcon" class="w-3 h-3" />
                    <span class="font-semibold">{{ trend.value }}%</span>
                </div>
            </div>

            <!-- Title -->
            <h3 class="metadata mb-2">{{ title }}</h3>

            <!-- Value -->
            <div :class="[textGradientClasses, 'financial-number text-4xl mb-2']">
                {{ value }}
            </div>

            <!-- Trend Label -->
            <p v-if="trend" class="text-xs text-gray-600 dark:text-gray-400">
                {{ trend.label }}
            </p>
        </div>
    </div>
</template>
