<script setup>
import { inject } from 'vue';
import { useForm, router } from '@inertiajs/vue3';
import { useConfirm } from '@/Composables/useConfirm';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Button from '@/Components/UI/Button.vue';
import Badge from '@/Components/UI/Badge.vue';
import Input from '@/Components/UI/Input.vue';
import InputLabel from '@/Components/UI/InputLabel.vue';
import InputError from '@/Components/UI/InputError.vue';
import Card from '@/Components/UI/Card.vue';
import Alert from '@/Components/UI/Alert.vue';
import Modal from '@/Components/UI/Modal.vue';
import { ArrowLeftIcon, InformationCircleIcon, ExclamationTriangleIcon, TrashIcon, CalendarIcon, CurrencyDollarIcon, BuildingOfficeIcon } from '@heroicons/vue/24/outline';

// Ziggy route helper
const route = inject('route');

const props = defineProps({
    trip: {
        type: Object,
        required: true
    },
    companies: {
        type: Array,
        required: true
    }
});

const form = useForm({
    expected_revision: props.trip.revision,
    nombre: props.trip.nombre,
    descripcion: props.trip.descripcion || '',
    company_id: props.trip.company_id || '',
    fecha_inicio: props.trip.fecha_inicio || '',
    fecha_fin: props.trip.fecha_fin || '',
    moneda_trabajo: props.trip.moneda_trabajo,
    tipo_cambio: props.trip.tipo_cambio,
    estado: props.trip.estado
});

const { show: showConfirm, isOpen, message, title, confirm, cancel } = useConfirm();

const submit = () => {
    form.put(route('trips.update', props.trip.id));
};

const deleteTrip = () => {
    showConfirm({
        title: 'Mover a la papelera',
        message: `¿Deseas mover a la papelera la importación "${props.trip.nombre}"? La importación pasará a la papelera y conservará sus productos, gastos e historial.`,
        onConfirm: () => {
            router.delete(route('trips.destroy', props.trip.id), { data: { expected_revision: props.trip.revision } });
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

const hasAssociatedData = props.trip.expenses?.length > 0 || props.trip.products?.length > 0;
</script>

<template>
    <AuthenticatedLayout>
        <!-- Back button -->
        <div class="mb-6">
            <Button
                variant="ghost"
                size="sm"
                @click="router.visit(route('trips.show', trip.id))"
            >
                <ArrowLeftIcon class="h-4 w-4" />
                Volver a Importación
            </Button>
        </div>

        <!-- Header -->
        <div class="mb-8">
            <h1 class="text-3xl font-bold text-gray-900 dark:text-white">
                Editar Importación
            </h1>
            <p class="mt-1 text-sm text-gray-600 dark:text-slate-400">
                Modifica la información de la importación
            </p>
        </div>

        <!-- Warning Alert -->
        <Alert v-if="hasAssociatedData" type="warning" class="max-w-3xl mb-6">
            <div class="flex items-start gap-2">
                <ExclamationTriangleIcon class="h-5 w-5 flex-shrink-0" />
                <div class="text-sm">
                    <p class="font-medium mb-1">Esta importación tiene datos asociados</p>
                    <p>
                        Se encontraron {{ trip.expenses?.length || 0 }} gastos y {{ trip.products?.length || 0 }} productos.
                        Al eliminar esta importación, todos estos datos se perderán permanentemente.
                    </p>
                </div>
            </div>
        </Alert>

        <form @submit.prevent="submit" class="max-w-3xl space-y-6">
            <!-- Información General -->
            <Card class="animate-fade-in-up">
                <div class="card-header">
                    <h2 class="metadata text-gray-900 dark:text-white">
                        Información General
                    </h2>
                </div>

                <div class="card-body space-y-4">
                    <!-- Nombre -->
                    <div>
                        <InputLabel for="nombre" value="Nombre de la Importación" required />
                        <Input
                            id="nombre"
                            v-model="form.nombre"
                            type="text"
                            placeholder="Ej: Importación Enero 2025"
                            :error="form.errors.nombre"
                            required
                        />
                        <InputError :message="form.errors.nombre" />
                    </div>

                    <!-- Descripción -->
                    <div>
                        <InputLabel for="descripcion" value="Descripción (opcional)" />
                        <textarea
                            id="descripcion"
                            v-model="form.descripcion"
                            rows="3"
                            class="input"
                            placeholder="Describe los detalles de esta importación..."
                        />
                        <InputError :message="form.errors.descripcion" />
                    </div>

                    <!-- Empresa -->
                    <div>
                        <InputLabel for="company_id" value="Empresa (opcional)" />
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <BuildingOfficeIcon class="h-5 w-5 text-gray-400" />
                            </div>
                            <select
                                id="company_id"
                                v-model="form.company_id"
                                class="select pl-10"
                            >
                                <option value="">Sin empresa asignada</option>
                                <option
                                    v-for="company in companies"
                                    :key="company.id"
                                    :value="company.id"
                                >
                                    {{ company.nombre }}
                                </option>
                            </select>
                        </div>
                        <InputError :message="form.errors.company_id" />
                    </div>

                    <!-- Estado -->
                    <div>
                        <InputLabel for="estado" value="Estado de la Importación" required />
                        <div class="flex gap-3 items-start">
                            <select
                                id="estado"
                                v-model="form.estado"
                                class="select flex-1"
                                required
                            >
                                <option value="activo">Activo</option>
                                <option value="finalizado">Finalizado</option>
                                <option value="archivado">Archivado</option>
                            </select>
                            <div class="pt-2">
                                <Badge :variant="getBadgeVariant(form.estado)">
                                    {{ form.estado }}
                                </Badge>
                            </div>
                        </div>
                        <InputError :message="form.errors.estado" />
                        <p class="mt-1 text-xs text-gray-500 dark:text-slate-400">
                            Las importaciones finalizadas no permiten agregar más gastos o productos
                        </p>
                    </div>

                    <!-- Fechas -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <InputLabel for="fecha_inicio" value="Fecha de Inicio (opcional)" />
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                    <CalendarIcon class="h-5 w-5 text-gray-400" />
                                </div>
                                <Input
                                    id="fecha_inicio"
                                    v-model="form.fecha_inicio"
                                    type="date"
                                    :error="form.errors.fecha_inicio"
                                    class="pl-10"
                                />
                            </div>
                            <InputError :message="form.errors.fecha_inicio" />
                        </div>

                        <div>
                            <InputLabel for="fecha_fin" value="Fecha de Fin (opcional)" />
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                    <CalendarIcon class="h-5 w-5 text-gray-400" />
                                </div>
                                <Input
                                    id="fecha_fin"
                                    v-model="form.fecha_fin"
                                    type="date"
                                    :error="form.errors.fecha_fin"
                                    :min="form.fecha_inicio"
                                    class="pl-10"
                                />
                            </div>
                            <InputError :message="form.errors.fecha_fin" />
                        </div>
                    </div>
                </div>
            </Card>

            <!-- Configuración de Moneda -->
            <Card class="animate-fade-in-up" style="animation-delay: 0.1s;">
                <div class="card-header">
                    <h2 class="metadata text-gray-900 dark:text-white">
                        Configuración de Moneda
                    </h2>
                </div>

                <div class="card-body">
                    <Alert type="warning" class="mb-4">
                        <div class="flex items-start gap-2">
                            <ExclamationTriangleIcon class="h-5 w-5 flex-shrink-0" />
                            <div class="text-sm">
                                <p class="font-medium mb-1">Importante: Recalculo Automático</p>
                                <p>Si modificas el tipo de cambio, todos los cálculos de prorrateo se recalcularán automáticamente. Esto afectará los costos finales de todos los productos.</p>
                            </div>
                        </div>
                    </Alert>

                    <div class="space-y-4">
                    <!-- Moneda de Trabajo -->
                    <div>
                        <InputLabel for="moneda_trabajo" value="Moneda de Trabajo" required />
                        <select
                            id="moneda_trabajo"
                            v-model="form.moneda_trabajo"
                            class="select"
                            required
                        >
                            <option value="USD">USD - Dólar Estadounidense</option>
                            <option value="BRL">BRL - Real Brasileño</option>
                            <option value="PEN">PEN - Sol Peruano</option>
                        </select>
                        <InputError :message="form.errors.moneda_trabajo" />
                    </div>

                    <!-- Tipo de Cambio -->
                    <div>
                        <InputLabel for="tipo_cambio" value="Tipo de Cambio a CLP" required />
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <CurrencyDollarIcon class="h-5 w-5 text-gray-400" />
                            </div>
                            <Input
                                id="tipo_cambio"
                                v-model="form.tipo_cambio"
                                type="number"
                                step="0.01"
                                min="0"
                                placeholder="950.00"
                                :error="form.errors.tipo_cambio"
                                required
                                class="pl-10"
                            />
                        </div>
                        <InputError :message="form.errors.tipo_cambio" />
                        <p class="mt-1 text-xs text-gray-500 dark:text-slate-400">
                            Tipo de cambio actual de {{ form.moneda_trabajo }} a CLP
                        </p>
                    </div>
                    </div>
                </div>
            </Card>

            <!-- Actions -->
            <div class="flex items-center justify-between animate-fade-in-up" style="animation-delay: 0.2s;">
                <Button
                    variant="danger"
                    type="button"
                    @click="deleteTrip"
                >
                    <TrashIcon class="h-4 w-4" />
                    Mover a la papelera
                </Button>

                <div class="flex items-center gap-4">
                    <Button
                        variant="ghost"
                        type="button"
                        @click="router.visit(route('trips.show', trip.id))"
                    >
                        Cancelar
                    </Button>
                    <Button
                        variant="primary"
                        type="submit"
                        :loading="form.processing"
                        :disabled="form.processing"
                    >
                        Guardar Cambios
                    </Button>
                </div>
            </div>
        </form>

        <!-- Confirmation Modal -->
        <Modal :show="isOpen" @close="cancel">
            <template #header>
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                    {{ title }}
                </h3>
            </template>

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
