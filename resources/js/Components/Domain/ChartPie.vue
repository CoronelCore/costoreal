<script setup>
import { ref, onMounted, watch, onUnmounted } from 'vue';
import { Chart, ArcElement, Tooltip, Legend, DoughnutController } from 'chart.js';
import { useDarkMode } from '@/Composables/useDarkMode';

Chart.register(ArcElement, Tooltip, Legend, DoughnutController);

const props = defineProps({
    data: {
        type: Array,
        required: true
    },
    labels: {
        type: Array,
        required: true
    },
    colors: {
        type: Array,
        default: () => []
    },
    title: {
        type: String,
        default: ''
    }
});

const { isDark } = useDarkMode();
const chartCanvas = ref(null);
let chartInstance = null;

const createChart = () => {
    if (!chartCanvas.value) return;

    const ctx = chartCanvas.value.getContext('2d');

    // Destruir instancia anterior si existe
    if (chartInstance) {
        chartInstance.destroy();
    }

    // Premium color palettes
    const lightModeColors = ['#3b82f6', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6', '#ec4899', '#06b6d4', '#f97316', '#6366f1', '#14b8a6', '#f43f5e', '#84cc16'];
    const darkModeColors = ['#60a5fa', '#34d399', '#fbbf24', '#f87171', '#a78bfa', '#f472b6', '#22d3ee', '#fb923c', '#818cf8', '#2dd4bf', '#fb7185', '#a3e635'];

    const chartColors = props.colors.length > 0
        ? props.colors
        : (isDark.value ? darkModeColors : lightModeColors);

    const textColor = isDark.value ? '#F1F5F9' : '#1F2937';
    const gridColor = isDark.value ? 'rgba(148, 163, 184, 0.1)' : 'rgba(203, 213, 225, 0.3)';

    chartInstance = new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: props.labels,
            datasets: [{
                data: props.data,
                backgroundColor: chartColors,
                borderColor: isDark.value ? '#0F172A' : '#FFFFFF',
                borderWidth: 2,
                hoverOffset: 10
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: {
                        color: textColor,
                        padding: 20,
                        font: {
                            size: 13,
                            weight: '500',
                            family: "'Inter', sans-serif"
                        },
                        usePointStyle: true,
                        pointStyle: 'circle'
                    }
                },
                tooltip: {
                    backgroundColor: isDark.value ? '#1E293B' : '#FFFFFF',
                    titleColor: textColor,
                    bodyColor: textColor,
                    borderColor: isDark.value ? '#334155' : '#E5E7EB',
                    borderWidth: 1,
                    padding: 16,
                    cornerRadius: 12,
                    displayColors: true,
                    titleFont: {
                        size: 14,
                        weight: '600'
                    },
                    bodyFont: {
                        size: 13
                    },
                    callbacks: {
                        label: function(context) {
                            const label = context.label || '';
                            const value = context.parsed || 0;
                            const total = context.dataset.data.reduce((a, b) => a + b, 0);
                            const percentage = ((value / total) * 100).toFixed(1);
                            return `${label}: $${value.toLocaleString('es-CL')} (${percentage}%)`;
                        }
                    }
                }
            },
            cutout: '65%',
            animation: {
                animateRotate: true,
                animateScale: true,
                duration: 1200,
                easing: 'easeInOutQuart'
            }
        }
    });
};

onMounted(() => {
    createChart();
});

// Recrear gráfico cuando cambie el tema
watch(isDark, () => {
    createChart();
});

// Recrear gráfico cuando cambien los datos
watch(() => [props.data, props.labels], () => {
    createChart();
}, { deep: true });

onUnmounted(() => {
    if (chartInstance) {
        chartInstance.destroy();
    }
});
</script>

<template>
    <div class="relative w-full h-full">
        <canvas ref="chartCanvas"></canvas>
    </div>
</template>
