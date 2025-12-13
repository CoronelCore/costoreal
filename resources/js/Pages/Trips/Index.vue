<script setup>
import { inject } from 'vue';
import { router } from '@inertiajs/vue3';
import { useConfirm } from '@/Composables/useConfirm';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Button from '@/Components/UI/Button.vue';
import Badge from '@/Components/UI/Badge.vue';
import Card from '@/Components/UI/Card.vue';
import Modal from '@/Components/UI/Modal.vue';
import CompanyIcon from '@/Components/Domain/CompanyIcon.vue';
import {
    PlusIcon,
    TruckIcon,
    EyeIcon,
    PencilIcon,
    TrashIcon,
    CalendarIcon,
    CurrencyDollarIcon,
    ShoppingBagIcon,
    ReceiptPercentIcon,
} from '@heroicons/vue/24/outline';

// Ziggy route helper
const route = inject('route');

const props = defineProps({
    trips: {
        type: Array,
        required: true
    }
});

const { show: showConfirm, isOpen, message, title, confirm, cancel } = useConfirm();

const deleteTrip = (trip) => {
    showConfirm({
        title: 'Eliminar Importación',
        message: `¿Estás seguro de que deseas eliminar la importación "${trip.nombre}"? Esta acción no se puede deshacer y eliminará todos los gastos y productos asociados.`,
        onConfirm: () => {
            router.delete(route('trips.destroy', trip.id), {
                preserveScroll: true,
            });
        }
    });
};

const getBadgeVariant = (estado) => {
    return {
        'activo': 'info',
        'finalizado': 'success',
        'archivado': 'gray'
    }[estado] || 'gray';
};

const formatDate = (date) => {
    if (!date) return 'No especificada';
    return new Date(date).toLocaleDateString('es-CL', {
        year: 'numeric',
        month: 'short',
        day: 'numeric'
    });
};

const formatCurrency = (value) => {
    return new Intl.NumberFormat('es-CL', {
        minimumFractionDigits: 0,
        maximumFractionDigits: 0
    }).format(value);
};
</script>

<template>
    <AuthenticatedLayout>
        <!-- Header -->
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-8">
            <div>
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white">
                    Importaciones
                </h1>
                <p class="text-sm text-gray-600 dark:text-slate-400 mt-1">
                    Gestiona tus viajes de compras y calcula costos
                </p>
            </div>
            <Button
                @click="router.visit(route('trips.create'))"
                variant="primary"
            >
                <PlusIcon class="h-5 w-5 mr-2" />
                Nueva Importación
            </Button>
        </div>

        <!-- Grid de Trips -->
        <div v-if="trips.length > 0" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            <Card
                v-for="(trip, index) in trips"
                :key="trip.id"
                :hover="true"
                class="group cursor-pointer"
                @click="router.visit(route('trips.show', trip.id))"
            >
                <div class="flex justify-between items-start mb-4">
                    <div class="flex items-center gap-3">
                        <div 
                            class="w-10 h-10 rounded-lg flex items-center justify-center text-white shadow-lg"
                            :style="{ backgroundColor: trip.company?.color || '#3b82f6' }"
                        >
                            <CompanyIcon :icon="trip.company?.icono" color="white" size="md" />
                        </div>
                        <div>
                            <h3 class="font-semibold text-gray-900 dark:text-white group-hover:text-primary-600 dark:group-hover:text-primary-400 transition-colors">
                                {{ trip.nombre }}
                            </h3>
                            <p class="text-xs text-gray-500 dark:text-slate-400">
                                {{ trip.company?.nombre || 'Sin Empresa' }}
                            </p>
                        </div>
                    </div>
                    <Badge :variant="getBadgeVariant(trip.estado)">
                        {{ trip.estado }}
                    </Badge>
                </div>

                <div class="space-y-3">
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-500 dark:text-slate-400">Fecha Inicio</span>
                        <span class="font-medium text-gray-900 dark:text-white">
                            {{ formatDate(trip.fecha_inicio) }}
                        </span>
                    </div>
                    
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-500 dark:text-slate-400">Inversión Total</span>
                        <span class="font-bold text-gray-900 dark:text-white">
                            ${{ formatCurrency(trip.total_gastos_clp || 0) }}
                        </span>
                    </div>

                    <div class="pt-3 border-t border-gray-100 dark:border-slate-700">
                        <div class="flex items-center justify-between text-xs text-gray-500 dark:text-slate-400">
                            <div class="flex items-center gap-1">
                                <ShoppingBagIcon class="h-3 w-3" />
                                {{ trip.products_count || 0 }} productos
                            </div>
                            <div class="flex items-center gap-1">
                                <ReceiptPercentIcon class="h-3 w-3" />
                                {{ trip.expenses_count || 0 }} gastos
                            </div>
                        </div>
                    </div>
                </div>
            </Card>
        </div>

        <!-- Empty State -->
        <Card v-else class="text-center py-12">
            <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-primary-50 dark:bg-primary-900/20 mb-4">
                <CurrencyDollarIcon class="h-8 w-8 text-primary-500" />
            </div>
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-2">
                No hay importaciones
            </h3>
            <p class="text-gray-500 dark:text-slate-400 mb-6 max-w-sm mx-auto">
                Comienza creando tu primera importación para gestionar gastos y calcular costos.
            </p>
            <Button @click="router.visit(route('trips.create'))" variant="primary">
                <PlusIcon class="h-5 w-5 mr-2" />
                Crear Importación
            </Button>
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
