<script setup>
import { computed, inject } from 'vue';
import { router, Link } from '@inertiajs/vue3';
import { useConfirm } from '@/Composables/useConfirm';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Button from '@/Components/UI/Button.vue';
import Badge from '@/Components/UI/Badge.vue';
import Card from '@/Components/UI/Card.vue';
import Modal from '@/Components/UI/Modal.vue';
import CompanyIcon from '@/Components/Domain/CompanyIcon.vue';
import KpiCard from '@/Components/Domain/KpiCard.vue';
import NumberDisplay from '@/Components/UI/NumberDisplay.vue';
import {
    ArrowLeftIcon,
    PencilIcon,
    TrashIcon,
    TruckIcon,
    CurrencyDollarIcon,
    ChartBarIcon,
    CalendarIcon
} from '@heroicons/vue/24/outline';

// Ziggy route helper
const route = inject('route');

const props = defineProps({
    company: {
        type: Object,
        required: true
    }
});

const { show: showConfirm, isOpen, message, title, confirm, cancel } = useConfirm();

// Calcular estadísticas
const totalGastado = computed(() => {
    if (!props.company.trips || props.company.trips.length === 0) return 0;
    return props.company.trips.reduce((sum, trip) => {
        // Aquí deberías calcular el total gastado real del trip
        // Por ahora solo retorno 0
        return sum;
    }, 0);
});

const promedioImportacion = computed(() => {
    const count = props.company.trips?.length || 0;
    return count > 0 ? totalGastado.value / count : 0;
});

const formatCurrency = (value) => {
    return new Intl.NumberFormat('es-CL', {
        style: 'currency',
        currency: 'CLP'
    }).format(value);
};

const formatDate = (date) => {
    return new Date(date).toLocaleDateString('es-CL', {
        year: 'numeric',
        month: 'long',
        day: 'numeric'
    });
};

const deleteCompany = () => {
    showConfirm({
        title: 'Eliminar Empresa',
        message: `¿Estás seguro de que deseas eliminar la empresa "${props.company.nombre}"? Esta acción no se puede deshacer y eliminará todas las importaciones asociadas.`,
        onConfirm: () => {
            router.delete(route('companies.destroy', props.company.id));
        }
    });
};
</script>

<template>
    <AuthenticatedLayout>
        <!-- Back button -->
        <div class="mb-6">
            <Button
                variant="ghost"
                size="sm"
                @click="router.visit(route('companies.index'))"
            >
                <ArrowLeftIcon class="h-4 w-4" />
                Volver a Empresas
            </Button>
        </div>

        <!-- Header with Gradient Background -->
        <div class="card overflow-hidden mb-8">
            <!-- Gradient Header -->
            <div class="relative bg-gradient-to-br from-primary-500 via-primary-600 to-primary-700 p-8">
                <!-- Decorative pattern overlay -->
                <div class="absolute inset-0 bg-grid-white/10"></div>

                <div class="relative flex items-start justify-between">
                    <div class="flex items-start gap-6">
                        <!-- Large Company Icon -->
                        <div class="flex-shrink-0 p-6 rounded-2xl bg-white/10 backdrop-blur-sm shadow-xl border border-white/20">
                            <CompanyIcon
                                :icon="company.icono"
                                :color="company.color"
                                size="2xl"
                                class="w-16 h-16"
                            />
                        </div>
                        <div>
                            <div class="flex items-center gap-3 mb-2">
                                <h1 class="text-4xl font-bold text-white">
                                    {{ company.nombre }}
                                </h1>
                                <Badge :variant="company.activa ? 'success' : 'gray'" class="text-sm">
                                    {{ company.activa ? 'Activa' : 'Inactiva' }}
                                </Badge>
                            </div>
                            <p v-if="company.rut" class="text-primary-100 font-medium">
                                RUT: {{ company.rut }}
                            </p>
                            <p v-if="company.descripcion" class="mt-3 text-primary-50 max-w-2xl">
                                {{ company.descripcion }}
                            </p>
                        </div>
                    </div>

                    <div class="flex items-center gap-2">
                        <Button
                            variant="secondary"
                            @click="router.visit(route('companies.edit', company.id))"
                        >
                            <PencilIcon class="h-5 w-5" />
                            Editar
                        </Button>
                        <Button
                            variant="danger"
                            @click="deleteCompany"
                        >
                            <TrashIcon class="h-5 w-5" />
                            Eliminar
                        </Button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Estadísticas with KpiCard -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
            <!-- Total Importaciones -->
            <KpiCard
                title="Total Importaciones"
                :value="company.trips?.length || 0"
                :icon="TruckIcon"
                variant="primary"
            />

            <!-- Total Gastado -->
            <KpiCard
                title="Total Gastado"
                :value="formatCurrency(totalGastado)"
                :icon="CurrencyDollarIcon"
                variant="success"
            />

            <!-- Promedio por Importación -->
            <KpiCard
                title="Promedio por Importación"
                :value="formatCurrency(promedioImportacion)"
                :icon="ChartBarIcon"
                variant="primary"
            />
        </div>

        <!-- Importaciones -->
        <Card>
            <div class="card-header">
                <h2 class="text-xl font-semibold text-gray-900 dark:text-white">
                    Importaciones de esta Empresa
                </h2>
            </div>

            <div v-if="company.trips && company.trips.length > 0" class="divide-y divide-gray-200 dark:divide-slate-700">
                <Link
                    v-for="trip in company.trips"
                    :key="trip.id"
                    :href="route('trips.show', trip.id)"
                    class="block p-6 hover:bg-gray-50 dark:hover:bg-slate-800/50 transition-all duration-200 group"
                >
                    <div class="flex items-center justify-between">
                        <div class="flex-1">
                            <div class="flex items-center gap-3 mb-2">
                                <h3 class="text-lg font-semibold text-gray-900 dark:text-white group-hover:text-primary-600 dark:group-hover:text-primary-400 transition-colors">
                                    {{ trip.nombre }}
                                </h3>
                                <Badge
                                    :variant="trip.estado === 'activo' ? 'info' : 'success'"
                                >
                                    {{ trip.estado }}
                                </Badge>
                            </div>
                            <p v-if="trip.descripcion" class="text-sm text-gray-600 dark:text-slate-400 mb-3">
                                {{ trip.descripcion }}
                            </p>
                            <div class="flex items-center gap-4 text-sm text-gray-600 dark:text-slate-400">
                                <div class="flex items-center gap-1">
                                    <CalendarIcon class="h-4 w-4" />
                                    <span>{{ formatDate(trip.created_at) }}</span>
                                </div>
                                <div class="flex items-center gap-1">
                                    <CurrencyDollarIcon class="h-4 w-4" />
                                    <span>{{ trip.moneda_trabajo }}</span>
                                </div>
                            </div>
                        </div>
                        <div class="text-right ml-6">
                            <p class="text-xs text-gray-500 dark:text-slate-500 mb-1">
                                Tipo de cambio
                            </p>
                            <NumberDisplay
                                :value="parseFloat(trip.tipo_cambio)"
                                :currency="true"
                                size="lg"
                            />
                        </div>
                    </div>
                </Link>
            </div>

            <!-- Empty state -->
            <div v-else class="card-body text-center py-12">
                <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-gray-100 dark:bg-slate-800 mb-4">
                    <TruckIcon class="h-8 w-8 text-gray-400" />
                </div>
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-2">
                    No hay importaciones
                </h3>
                <p class="text-sm text-gray-600 dark:text-slate-400 mb-6">
                    Esta empresa aún no tiene importaciones asignadas
                </p>
                <Button
                    variant="primary"
                    @click="router.visit(route('trips.create'))"
                >
                    <TruckIcon class="h-5 w-5" />
                    Crear Importación
                </Button>
            </div>
        </Card>

        <!-- Confirmation Modal -->
        <Modal :show="isOpen" @close="cancel" :title="title">
            <p class="text-gray-600 dark:text-slate-400">
                {{ message }}
            </p>

            <template #footer>
                <div class="flex items-center justify-end gap-3">
                    <Button variant="secondary" @click="cancel">
                        Cancelar
                    </Button>
                    <Button variant="danger" @click="confirm">
                        Eliminar
                    </Button>
                </div>
            </template>
        </Modal>
    </AuthenticatedLayout>
</template>
