<script setup>
import { inject } from 'vue';
import { useForm, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Button from '@/Components/UI/Button.vue';
import Input from '@/Components/UI/Input.vue';
import InputLabel from '@/Components/UI/InputLabel.vue';
import InputError from '@/Components/UI/InputError.vue';
import Card from '@/Components/UI/Card.vue';
import Alert from '@/Components/UI/Alert.vue';
import CompanyIcon from '@/Components/Domain/CompanyIcon.vue';
import { ArrowLeftIcon, InformationCircleIcon, CalendarIcon, CurrencyDollarIcon, BuildingOfficeIcon } from '@heroicons/vue/24/outline';

// Ziggy route helper
const route = inject('route');

const props = defineProps({
    companies: {
        type: Array,
        required: true
    },
    selectedCompanyId: {
        type: Number,
        default: null
    }
});

const form = useForm({
    nombre: '',
    descripcion: '',
    company_id: props.selectedCompanyId || '',
    fecha_inicio: '',
    fecha_fin: '',
    moneda_trabajo: 'USD',
    tipo_cambio: 950.00
});

const submit = () => {
    form.post(route('trips.store'));
};
</script>

<template>
    <AuthenticatedLayout>
        <!-- Back button -->
        <div class="mb-6">
            <Button
                variant="ghost"
                size="sm"
                @click="router.visit(route('trips.index'))"
            >
                <ArrowLeftIcon class="h-4 w-4" />
                Volver a Importaciones
            </Button>
        </div>

        <!-- Header -->
        <div class="mb-8">
            <h1 class="text-3xl font-bold text-gray-900 dark:text-white">
                Nueva Importación
            </h1>
            <p class="mt-1 text-sm text-gray-600 dark:text-slate-400">
                Crea una nueva importación para calcular costos reales
            </p>
        </div>

        <form @submit.prevent="submit" class="max-w-3xl space-y-6">
            <!-- Información General -->
            <Card class="animate-fade-in-up">
                <div class="card-header">
                    <h2 class="metadata text-gray-900 dark:text-white">
                        Información General
                    </h2>
                    <p class="text-sm text-gray-600 dark:text-slate-400 mt-1">
                        Datos básicos de la importación
                    </p>
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
                        <p class="mt-1 text-xs text-gray-500 dark:text-slate-400">
                            Puedes asignar esta importación a una empresa para mejor organización
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
                    <Alert type="info" class="mb-4">
                        <div class="flex items-start gap-2">
                            <InformationCircleIcon class="h-5 w-5 flex-shrink-0" />
                            <div class="text-sm">
                                <p class="font-medium mb-1">Moneda Base: CLP</p>
                                <p>Todos los cálculos finales se realizarán en pesos chilenos. Selecciona la moneda de trabajo de tu importación y el tipo de cambio actual.</p>
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
                            Ingresa el tipo de cambio actual de {{ form.moneda_trabajo }} a CLP
                        </p>
                    </div>
                    </div>
                </div>
            </Card>

            <!-- Actions -->
            <div class="flex items-center justify-end gap-4 animate-fade-in-up" style="animation-delay: 0.2s;">
                <Button
                    variant="ghost"
                    type="button"
                    @click="router.visit(route('trips.index'))"
                >
                    Cancelar
                </Button>
                <Button
                    variant="primary"
                    type="submit"
                    :loading="form.processing"
                    :disabled="form.processing"
                >
                    Crear Importación
                </Button>
            </div>
        </form>
    </AuthenticatedLayout>
</template>
