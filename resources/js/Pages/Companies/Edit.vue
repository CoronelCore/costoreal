<script setup>
import { ref, computed, inject } from 'vue';
import { useForm, router } from '@inertiajs/vue3';
import { useConfirm } from '@/Composables/useConfirm';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Button from '@/Components/UI/Button.vue';
import Input from '@/Components/UI/Input.vue';
import InputLabel from '@/Components/UI/InputLabel.vue';
import InputError from '@/Components/UI/InputError.vue';
import Card from '@/Components/UI/Card.vue';
import Modal from '@/Components/UI/Modal.vue';
import Badge from '@/Components/UI/Badge.vue';
import CompanyIcon from '@/Components/Domain/CompanyIcon.vue';
import { ArrowLeftIcon, TrashIcon, TruckIcon } from '@heroicons/vue/24/outline';

// Ziggy route helper
const route = inject('route');

const props = defineProps({
    company: {
        type: Object,
        required: true
    }
});

// Available icons
const availableIcons = [
    'BuildingOfficeIcon',
    'TruckIcon',
    'ShoppingCartIcon',
    'GlobeAltIcon',
    'CubeIcon',
    'SparklesIcon',
    'RocketLaunchIcon',
    'BriefcaseIcon',
    'HomeModernIcon',
    'BeakerIcon',
    'Cog6ToothIcon',
    'LightBulbIcon',
];

// Suggested colors
const suggestedColors = [
    '#6366f1', // Indigo
    '#8b5cf6', // Purple
    '#ec4899', // Pink
    '#ef4444', // Red
    '#f59e0b', // Amber
    '#10b981', // Green
    '#3b82f6', // Blue
    '#14b8a6', // Teal
];

const form = useForm({
    nombre: props.company.nombre,
    rut: props.company.rut || '',
    descripcion: props.company.descripcion || '',
    color: props.company.color,
    icono: props.company.icono || 'BuildingOfficeIcon',
    activa: props.company.activa
});

const { show: showConfirm, isOpen, message, title, confirm, cancel } = useConfirm();

const submit = () => {
    form.put(route('companies.update', props.company.id));
};

const deleteCompany = () => {
    showConfirm({
        title: 'Eliminar Empresa',
        message: `¿Estás seguro de que deseas eliminar la empresa "${props.company.nombre}"? Esta acción no se puede deshacer.`,
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

        <!-- Header -->
        <div class="mb-8">
            <h1 class="text-3xl font-bold text-gray-900 dark:text-white">
                Editar Empresa
            </h1>
            <p class="mt-1 text-sm text-gray-600 dark:text-slate-400">
                Modifica la información de la empresa
            </p>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <!-- Form -->
            <div class="lg:col-span-2">
                <form @submit.prevent="submit" class="space-y-6">
                    <!-- Información Básica -->
                    <Card>
                        <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">
                            Información de Empresa
                        </h2>

                        <div class="space-y-4">
                            <!-- Nombre -->
                            <div>
                                <InputLabel for="nombre" value="Nombre de la Empresa" required />
                                <Input
                                    id="nombre"
                                    v-model="form.nombre"
                                    type="text"
                                    placeholder="Mi Empresa S.A."
                                    :error="form.errors.nombre"
                                    required
                                />
                                <InputError :message="form.errors.nombre" />
                            </div>

                            <!-- RUT -->
                            <div>
                                <InputLabel for="rut" value="RUT (opcional)" />
                                <Input
                                    id="rut"
                                    v-model="form.rut"
                                    type="text"
                                    placeholder="12.345.678-9"
                                    :error="form.errors.rut"
                                />
                                <InputError :message="form.errors.rut" />
                            </div>

                            <!-- Descripción -->
                            <div>
                                <InputLabel for="descripcion" value="Descripción (opcional)" />
                                <textarea
                                    id="descripcion"
                                    v-model="form.descripcion"
                                    rows="3"
                                    class="input"
                                    placeholder="Describe brevemente la empresa..."
                                />
                                <InputError :message="form.errors.descripcion" />
                            </div>

                            <!-- Estado -->
                            <div>
                                <InputLabel for="activa" value="Estado de la Empresa" />
                                <select
                                    id="activa"
                                    v-model="form.activa"
                                    class="select"
                                >
                                    <option :value="true">Activa</option>
                                    <option :value="false">Inactiva</option>
                                </select>
                                <InputError :message="form.errors.activa" />
                            </div>
                        </div>
                    </Card>

                    <!-- Personalización Visual -->
                    <Card>
                        <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">
                            Personalización Visual
                        </h2>

                        <div class="space-y-4">
                            <!-- Color Picker with Live Preview -->
                            <div>
                                <InputLabel value="Color de la Empresa" required />
                                <div class="flex items-center gap-4">
                                    <div class="relative">
                                        <input
                                            id="color-picker"
                                            v-model="form.color"
                                            type="color"
                                            class="h-14 w-14 rounded-xl border-2 border-gray-300 dark:border-slate-600 cursor-pointer shadow-xs hover:shadow-md transition-shadow"
                                        />
                                        <div class="absolute -top-1 -right-1 w-4 h-4 bg-primary-500 rounded-full border-2 border-white dark:border-slate-800 animate-pulse"></div>
                                    </div>
                                    <Input
                                        v-model="form.color"
                                        type="text"
                                        placeholder="#6366f1"
                                        :error="form.errors.color"
                                        class="flex-1 font-mono"
                                        readonly
                                    />
                                </div>
                                <InputError :message="form.errors.color" />

                                <!-- Suggested Colors Palette -->
                                <div class="mt-4">
                                    <p class="text-sm font-medium text-gray-700 dark:text-slate-300 mb-3">
                                        Paleta de colores sugeridos:
                                    </p>
                                    <div class="grid grid-cols-8 gap-2">
                                        <button
                                            v-for="color in suggestedColors"
                                            :key="color"
                                            type="button"
                                            @click="form.color = color"
                                            :style="{ backgroundColor: color }"
                                            class="relative w-10 h-10 rounded-lg border-2 transition-all duration-200 hover:scale-110 hover:shadow-lg"
                                            :class="form.color === color ? 'border-gray-900 dark:border-white scale-110 shadow-lg ring-2 ring-offset-2 ring-gray-900 dark:ring-white' : 'border-transparent hover:border-gray-300 dark:hover:border-slate-600'"
                                        >
                                            <span v-if="form.color === color" class="absolute inset-0 flex items-center justify-center">
                                                <svg class="w-5 h-5 text-white drop-shadow-md" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path>
                                                </svg>
                                            </span>
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <!-- Icon Selector Grid with Visual Feedback -->
                            <div>
                                <InputLabel value="Icono de la Empresa" />
                                <div class="grid grid-cols-6 gap-3 mt-3">
                                    <button
                                        v-for="icon in availableIcons"
                                        :key="icon"
                                        type="button"
                                        @click="form.icono = icon"
                                        class="relative p-4 rounded-xl border-2 transition-all duration-200 hover:scale-105 hover:shadow-md group"
                                        :class="form.icono === icon
                                            ? 'border-primary-600 bg-primary-50 dark:bg-primary-900/30 shadow-md scale-105 ring-2 ring-primary-200 dark:ring-primary-800'
                                            : 'border-gray-300 dark:border-slate-600 hover:border-primary-300 dark:hover:border-primary-700 hover:bg-gray-50 dark:hover:bg-slate-800'"
                                    >
                                        <CompanyIcon
                                            :icon="icon"
                                            :color="form.color"
                                            size="md"
                                            class="transition-transform group-hover:scale-110"
                                        />
                                        <div
                                            v-if="form.icono === icon"
                                            class="absolute -top-1 -right-1 w-5 h-5 bg-primary-500 rounded-full flex items-center justify-center"
                                        >
                                            <svg class="w-3 h-3 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path>
                                            </svg>
                                        </div>
                                    </button>
                                </div>
                                <InputError :message="form.errors.icono" />
                            </div>
                        </div>
                    </Card>

                    <!-- Actions with Deactivate Button -->
                    <Card>
                        <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">
                            Acciones
                        </h2>

                        <div class="space-y-4">
                            <!-- Main Actions -->
                            <div class="flex items-center justify-between gap-4 pb-4 border-b border-gray-200 dark:border-slate-700">
                                <div class="flex items-center gap-3">
                                    <Button
                                        variant="secondary"
                                        type="button"
                                        @click="router.visit(route('companies.index'))"
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

                            <!-- Deactivate/Activate Company -->
                            <div class="flex items-center justify-between">
                                <div>
                                    <h3 class="text-sm font-medium text-gray-900 dark:text-white mb-1">
                                        Estado de la Empresa
                                    </h3>
                                    <p class="text-sm text-gray-600 dark:text-slate-400">
                                        {{ form.activa ? 'La empresa está activa y visible' : 'La empresa está inactiva y oculta' }}
                                    </p>
                                </div>
                                <Button
                                    variant="secondary"
                                    type="button"
                                    @click="form.activa = !form.activa"
                                >
                                    {{ form.activa ? 'Desactivar' : 'Activar' }}
                                </Button>
                            </div>
                        </div>
                    </Card>
                </form>
            </div>

            <!-- Real-time Preview Card -->
            <div class="lg:col-span-1">
                <div class="sticky top-24 space-y-6">
                    <Card class="overflow-hidden">
                        <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">
                            Vista Previa en Tiempo Real
                        </h2>

                        <!-- Preview Card with selected icon + color -->
                        <div class="relative p-6 bg-linear-to-br from-gray-50 to-gray-100 dark:from-slate-900 dark:to-slate-800 rounded-xl border-2 border-dashed border-gray-300 dark:border-slate-600">
                            <div class="flex items-start gap-4 mb-4">
                                <div class="relative shrink-0">
                                    <div class="p-4 rounded-xl bg-white dark:bg-slate-800 shadow-lg">
                                        <CompanyIcon
                                            :icon="form.icono"
                                            :color="form.color"
                                            size="xl"
                                        />
                                    </div>
                                    <!-- Color dot indicator -->
                                    <div
                                        class="absolute -bottom-1 -right-1 w-5 h-5 rounded-full border-2 border-white dark:border-slate-800 shadow-xs"
                                        :style="{ backgroundColor: form.color }"
                                    />
                                </div>
                                <div class="flex-1 min-w-0">
                                    <h3 class="text-xl font-bold text-gray-900 dark:text-white truncate mb-1">
                                        {{ form.nombre || 'Nombre de la empresa' }}
                                    </h3>
                                    <p v-if="form.rut" class="text-sm text-gray-600 dark:text-slate-400">
                                        RUT: {{ form.rut }}
                                    </p>
                                    <Badge :variant="form.activa ? 'success' : 'gray'" class="mt-2">
                                        {{ form.activa ? 'Activa' : 'Inactiva' }}
                                    </Badge>
                                </div>
                            </div>

                            <p
                                v-if="form.descripcion"
                                class="text-sm text-gray-600 dark:text-slate-400 mb-4 line-clamp-3"
                            >
                                {{ form.descripcion }}
                            </p>

                            <!-- Preview stats -->
                            <div class="flex items-center gap-2 pt-4 border-t border-gray-300 dark:border-slate-600">
                                <div class="flex items-center gap-2 px-3 py-2 rounded-lg bg-white dark:bg-slate-800 shadow-xs">
                                    <TruckIcon class="h-4 w-4" :style="{ color: form.color }" />
                                    <span class="text-sm font-semibold text-gray-900 dark:text-white">{{ company.trips_count || 0 }}</span>
                                    <span class="text-xs text-gray-600 dark:text-slate-400">importaciones</span>
                                </div>
                            </div>
                        </div>

                        <!-- Color info -->
                        <div class="mt-4 p-3 bg-primary-50 dark:bg-primary-900/20 rounded-lg">
                            <div class="flex items-center gap-2 text-sm">
                                <div class="w-3 h-3 rounded-full" :style="{ backgroundColor: form.color }"></div>
                                <span class="font-medium text-gray-700 dark:text-slate-300">Color seleccionado:</span>
                                <code class="px-2 py-1 bg-white dark:bg-slate-800 rounded-sm text-xs font-mono">{{ form.color }}</code>
                            </div>
                        </div>
                    </Card>

                    <!-- Danger Zone Card -->
                    <Card class="border-2 border-red-200 dark:border-red-900">
                        <h2 class="text-lg font-semibold text-red-600 dark:text-red-400 mb-4">
                            Zona de Peligro
                        </h2>

                        <p class="text-sm text-gray-600 dark:text-slate-400 mb-4">
                            Eliminar esta empresa es permanente y no se puede deshacer. Todas las importaciones asociadas también se eliminarán.
                        </p>

                        <Button
                            variant="danger"
                            @click="deleteCompany"
                            class="w-full"
                        >
                            <TrashIcon class="h-5 w-5" />
                            Eliminar Empresa Permanentemente
                        </Button>
                    </Card>
                </div>
            </div>
        </div>

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
