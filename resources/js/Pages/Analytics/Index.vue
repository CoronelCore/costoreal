<script setup>
import { computed, inject } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Card from '@/Components/UI/Card.vue';
import Badge from '@/Components/UI/Badge.vue';
import Button from '@/Components/UI/Button.vue';
import Alert from '@/Components/UI/Alert.vue';
import CategoryIcon from '@/Components/Domain/CategoryIcon.vue';
import CompanyIcon from '@/Components/Domain/CompanyIcon.vue';
import ChartPie from '@/Components/Domain/ChartPie.vue';
import ChartBar from '@/Components/Domain/ChartBar.vue';
import KpiCard from '@/Components/Domain/KpiCard.vue';
import NumberDisplay from '@/Components/UI/NumberDisplay.vue';
import {
    ChartBarIcon,
    BuildingOfficeIcon,
    CurrencyDollarIcon,
    ReceiptPercentIcon,
    ArrowTrendingUpIcon,
    XMarkIcon,
    TruckIcon,
} from '@heroicons/vue/24/outline';

// Ziggy route helper
const route = inject('route');

const props = defineProps({
    totalTrips: {
        type: Number,
        required: true
    },
    totalGastado: {
        type: Number,
        required: true
    },
    promedioViaje: {
        type: Number,
        required: true
    },
    viajeMasCostoso: {
        type: Object,
        default: null
    },
    gastosPorCategoria: {
        type: Array,
        required: true
    },
    topViajes: {
        type: Array,
        required: true
    },
    ultimosGastos: {
        type: Array,
        required: true
    },
    evolucionMensual: {
        type: Array,
        required: true
    }
});

const page = usePage();
const activeCompany = computed(() => page.props.auth?.activeCompany);

// DEBUG: Temporary console logs
console.log('=== ANALYTICS DEBUG ===');
console.log('totalTrips:', props.totalTrips);
console.log('totalGastado:', props.totalGastado);
console.log('viajeMasCostoso:', JSON.parse(JSON.stringify(props.viajeMasCostoso)));
console.log('gastosPorCategoria:', JSON.parse(JSON.stringify(props.gastosPorCategoria)));
console.log('topViajes:', JSON.parse(JSON.stringify(props.topViajes)));
console.log('ultimosGastos:', JSON.parse(JSON.stringify(props.ultimosGastos)));
console.log('evolucionMensual:', JSON.parse(JSON.stringify(props.evolucionMensual)));
console.log('======================');

// Preparar datos para gráfico de categorías
const chartCategoriesData = computed(() => {
    return props.gastosPorCategoria
        .filter(cat => cat.total_monto > 0)
        .map(cat => cat.total_monto);
});

const chartCategoriesLabels = computed(() => {
    return props.gastosPorCategoria
        .filter(cat => cat.total_monto > 0)
        .map(cat => cat.nombre);
});

const chartCategoriesColors = computed(() => {
    return props.gastosPorCategoria
        .filter(cat => cat.total_monto > 0)
        .map(cat => cat.color || '#6B7280');
});

// Preparar datos para gráfico de top viajes
const chartTopViajesData = computed(() => {
    return props.topViajes.map(trip => trip.costo_calculado || 0);
});

const chartTopViajesLabels = computed(() => {
    return props.topViajes.map(trip => {
        const maxLength = 25;
        return trip.nombre.length > maxLength
            ? trip.nombre.substring(0, maxLength) + '...'
            : trip.nombre;
    });
});

const formatCurrency = (value) => {
    return new Intl.NumberFormat('es-CL', {
        style: 'currency',
        currency: 'CLP',
        minimumFractionDigits: 0,
        maximumFractionDigits: 0
    }).format(value);
};

const formatDate = (date) => {
    if (!date) return '';
    return new Date(date).toLocaleDateString('es-CL', {
        year: 'numeric',
        month: 'short',
        day: 'numeric'
    });
};

const getRelativeTime = (date) => {
    const now = new Date();
    const past = new Date(date);
    const diffInSeconds = Math.floor((now - past) / 1000);

    if (diffInSeconds < 60) return 'Hace un momento';
    if (diffInSeconds < 3600) return `Hace ${Math.floor(diffInSeconds / 60)} min`;
    if (diffInSeconds < 86400) return `Hace ${Math.floor(diffInSeconds / 3600)} horas`;
    if (diffInSeconds < 604800) return `Hace ${Math.floor(diffInSeconds / 86400)} días`;
    return formatDate(date);
};

const clearCompanyFilter = () => {
    router.post(route('companies.switch'), { company_id: null });
};

const hasData = computed(() => {
    return props.totalTrips > 0 || props.ultimosGastos.length > 0;
});
</script>

<template>
    <AuthenticatedLayout>
        <!-- Header -->
        <div class="mb-8">
            <div class="flex items-start justify-between">
                <div>
                    <h1 class="text-3xl font-bold text-gradient-primary">
                        Analítica
                    </h1>
                    <p class="mt-1 metadata">
                        Visualiza estadísticas y tendencias de tus importaciones
                    </p>
                </div>
            </div>

            <!-- Company Filter Badge -->
            <div v-if="activeCompany" class="mt-4">
                <Alert type="info">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <CompanyIcon
                                :icon="activeCompany.icono"
                                :color="activeCompany.color"
                                size="sm"
                            />
                            <span class="text-sm">
                                Filtrando por empresa: <strong>{{ activeCompany.nombre }}</strong>
                            </span>
                        </div>
                        <Button
                            variant="ghost"
                            size="sm"
                            @click="clearCompanyFilter"
                        >
                            <XMarkIcon class="h-4 w-4" />
                            Limpiar filtro
                        </Button>
                    </div>
                </Alert>
            </div>
        </div>

        <template v-if="hasData">
            <!-- KPI Cards -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                <!-- Total Gastado -->
                <KpiCard
                    class="stagger-item"
                    title="Total Gastado"
                    :value="formatCurrency(totalGastado)"
                    :icon="CurrencyDollarIcon"
                    variant="primary"
                />

                <!-- Total Importaciones -->
                <KpiCard
                    class="stagger-item"
                    title="Total Importaciones"
                    :value="totalTrips"
                    :icon="TruckIcon"
                    variant="primary"
                />

                <!-- Promedio por Importación -->
                <KpiCard
                    class="stagger-item"
                    title="Promedio por Importación"
                    :value="formatCurrency(promedioViaje)"
                    :icon="ChartBarIcon"
                    variant="success"
                />

                <!-- Importación Más Costosa -->
                <KpiCard
                    v-if="viajeMasCostoso"
                    class="stagger-item"
                    title="Más Costosa"
                    :value="formatCurrency(viajeMasCostoso.costo_calculado || 0)"
                    :icon="ArrowTrendingUpIcon"
                    variant="warning"
                />
            </div>

            <!-- Gráficos -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
                <!-- Gráfico de Categorías -->
                <div class="card">
                    <div class="p-6">
                        <h2 class="metadata mb-4">
                            Distribución por Categoría
                        </h2>
                        <div v-if="chartCategoriesData.length > 0" class="h-80 flex items-center justify-center">
                            <ChartPie
                                :data="chartCategoriesData"
                                :labels="chartCategoriesLabels"
                                :colors="chartCategoriesColors"
                            />
                        </div>
                        <div v-else class="h-80 flex items-center justify-center">
                            <p class="text-gray-500 dark:text-slate-400 text-sm">
                                No hay datos de categorías disponibles
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Gráfico Top Viajes -->
                <div class="card">
                    <div class="p-6">
                        <h2 class="metadata mb-4">
                            Top 5 Importaciones Más Costosas
                        </h2>
                        <div v-if="topViajes.length > 0" class="h-80">
                            <ChartBar
                                :data="chartTopViajesData"
                                :labels="chartTopViajesLabels"
                                :horizontal="true"
                                color="#6366F1"
                            />
                        </div>
                        <div v-else class="h-80 flex items-center justify-center">
                            <p class="text-gray-500 dark:text-slate-400 text-sm">
                                No hay importaciones disponibles
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tablas -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
                <!-- Gastos por Categoría -->
                <Card>
                    <div class="card-header">
                        <h2 class="text-lg font-semibold text-gray-900 dark:text-white">
                            Gastos por Categoría
                        </h2>
                    </div>

                    <div v-if="gastosPorCategoria && gastosPorCategoria.length > 0" class="overflow-x-auto">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Categoría</th>
                                    <th class="text-center">Total Gastos</th>
                                    <th class="text-right">Monto Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="category in gastosPorCategoria" :key="category.id" class="hover:bg-gray-50 dark:hover:bg-slate-800/30 transition-colors">
                                    <td>
                                        <div class="flex items-center gap-2">
                                            <CategoryIcon
                                                :icon="category.icono"
                                                :color="category.color"
                                                size="sm"
                                            />
                                            <span class="font-medium">{{ category.nombre }}</span>
                                        </div>
                                    </td>
                                    <td class="text-center">
                                        <Badge variant="gray">{{ category.total_gastos }}</Badge>
                                    </td>
                                    <td class="text-right">
                                        <NumberDisplay
                                            :value="category.total_monto"
                                            :currency="true"
                                            size="sm"
                                        />
                                    </td>
                                </tr>
                            </tbody>
                            <tfoot>
                                <tr class="bg-gray-50 dark:bg-slate-900 font-semibold">
                                    <td>Total</td>
                                    <td class="text-center">
                                        {{ gastosPorCategoria.reduce((sum, cat) => sum + cat.total_gastos, 0) }}
                                    </td>
                                    <td class="text-right">
                                        <NumberDisplay
                                            :value="gastosPorCategoria.reduce((sum, cat) => sum + cat.total_monto, 0)"
                                            :currency="true"
                                            size="sm"
                                        />
                                    </td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>

                    <div v-else class="card-body text-center py-12">
                        <ReceiptPercentIcon class="h-12 w-12 text-gray-400 mx-auto mb-3" />
                        <p class="text-gray-500 dark:text-slate-400 text-sm">
                            No hay gastos registrados por categoría
                        </p>
                    </div>
                </Card>

                <!-- Últimos Gastos -->
                <Card>
                    <div class="card-header">
                        <h2 class="text-lg font-semibold text-gray-900 dark:text-white">
                            Últimos Gastos Registrados
                        </h2>
                    </div>

                    <div v-if="ultimosGastos && ultimosGastos.length > 0" class="divide-y divide-gray-200 dark:divide-slate-700">
                        <div
                            v-for="expense in ultimosGastos"
                            :key="expense.id"
                            class="p-4 hover:bg-gray-50 dark:hover:bg-slate-800/50 transition-all duration-200 hover:shadow-sm cursor-pointer"
                        >
                            <div class="flex items-start justify-between gap-3">
                                <div class="flex items-start gap-3 flex-1 min-w-0">
                                    <CategoryIcon
                                        v-if="expense.category"
                                        :icon="expense.category.icono"
                                        :color="expense.category.color"
                                        size="sm"
                                    />
                                    <div class="flex-1 min-w-0">
                                        <p class="font-medium text-gray-900 dark:text-white truncate">
                                            {{ expense.concepto }}
                                        </p>
                                        <p class="text-xs text-gray-500 dark:text-slate-400">
                                            {{ expense.category ? expense.category.nombre : 'Sin categoría' }}
                                        </p>
                                        <p class="text-xs text-gray-400 dark:text-slate-500 mt-1">
                                            {{ getRelativeTime(expense.created_at) }}
                                        </p>
                                    </div>
                                </div>
                                <div class="text-right flex-shrink-0">
                                    <NumberDisplay
                                        :value="expense.monto_clp"
                                        :currency="true"
                                        size="sm"
                                    />
                                    <p class="text-xs text-gray-500 dark:text-slate-400 mt-1">
                                        {{ expense.moneda }}
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div v-else class="card-body text-center py-12">
                        <ReceiptPercentIcon class="h-12 w-12 text-gray-400 mx-auto mb-3" />
                        <p class="text-gray-500 dark:text-slate-400 text-sm">
                            No hay gastos recientes
                        </p>
                    </div>
                </Card>
            </div>
        </template>

        <!-- Empty State -->
        <Card v-else>
            <div class="card-body text-center py-16">
                <div class="inline-flex items-center justify-center w-20 h-20 rounded-full bg-gray-100 dark:bg-slate-800 mb-4">
                    <ChartBarIcon class="h-10 w-10 text-gray-400" />
                </div>
                <h3 class="text-xl font-semibold text-gray-900 dark:text-white mb-2">
                    No hay datos disponibles
                </h3>
                <p class="text-sm text-gray-600 dark:text-slate-400 mb-6 max-w-md mx-auto">
                    Comienza creando tu primera importación y registrando gastos para ver estadísticas y gráficos aquí.
                </p>
                <Button
                    variant="primary"
                    @click="router.visit(route('trips.create'))"
                >
                    Crear Primera Importación
                </Button>
            </div>
        </Card>
    </AuthenticatedLayout>
</template>
