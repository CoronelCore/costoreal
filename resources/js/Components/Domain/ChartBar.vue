<script setup>
import { ref, onMounted, watch, onUnmounted } from 'vue';
import { Chart, BarElement, CategoryScale, LinearScale, Tooltip, Legend, BarController } from 'chart.js';
import { useDarkMode } from '@/Composables/useDarkMode';

Chart.register(BarElement, CategoryScale, LinearScale, Tooltip, Legend, BarController);

const props = defineProps({
    data: {
        type: Array,
        required: true
    },
    labels: {
        type: Array,
        required: true
    },
    color: {
        type: String,
        default: '#6366F1'
    },
    title: {
        type: String,
        default: ''
    },
    horizontal: {
        type: Boolean,
        default: false
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

    const textColor = isDark.value ? '#F1F5F9' : '#1F2937';
    const gridColor = isDark.value ? 'rgba(148, 163, 184, 0.1)' : 'rgba(203, 213, 225, 0.3)';

    chartInstance = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: props.labels,
            datasets: [{
                label: 'Costo Total',
                data: props.data,
                backgroundColor: props.color + 'CC', // 80% opacity
                borderColor: props.color,
                borderWidth: 2,
                borderRadius: 6,
                hoverBackgroundColor: props.color
            }]
        },
        options: {
            indexAxis: props.horizontal ? 'y' : 'x',
            responsive: true,
            maintainAspectRatio: true,
            plugins: {
                legend: {
                    position: 'bottom',
                    display: false,
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
                    titleFont: {
                        size: 14,
                        weight: '600'
                    },
                    bodyFont: {
                        size: 13
                    },
                    callbacks: {
                        label: function(context) {
                            return `$${context.parsed.y.toLocaleString('es-CL')}`;
                        }
                    }
                }
            },
            scales: {
                x: {
                    grid: {
                        color: gridColor,
                        display: !props.horizontal
                    },
                    ticks: {
                        color: textColor,
                        font: {
                            size: 11
                        }
                    },
                    border: {
                        color: gridColor
                    }
                },
                y: {
                    grid: {
                        color: gridColor,
                        display: props.horizontal
                    },
                    ticks: {
                        color: textColor,
                        font: {
                            size: 11
                        },
                        callback: function(value) {
                            if (props.horizontal) {
                                return this.getLabelForValue(value);
                            }
                            return '$' + value.toLocaleString('es-CL');
                        }
                    },
                    border: {
                        color: gridColor
                    }
                }
            },
            animation: {
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
