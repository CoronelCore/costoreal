<script setup>
import { inject } from 'vue';
import { useForm, router } from '@inertiajs/vue3';
import { useConfirm } from '@/Composables/useConfirm';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Button from '@/Components/UI/Button.vue';
import Input from '@/Components/UI/Input.vue';
import InputLabel from '@/Components/UI/InputLabel.vue';
import InputError from '@/Components/UI/InputError.vue';
import Card from '@/Components/UI/Card.vue';
import Modal from '@/Components/UI/Modal.vue';
import Alert from '@/Components/UI/Alert.vue';
import CategoryIcon from '@/Components/Domain/CategoryIcon.vue';
import { ArrowLeftIcon, TrashIcon, ExclamationTriangleIcon } from '@heroicons/vue/24/outline';

// Ziggy route helper
const route = inject('route');

const props = defineProps({
    category: {
        type: Object,
        required: true
    }
});

const form = useForm({
    nombre: props.category.nombre,
    icono: props.category.icono,
    color: props.category.color
});

const { show: showConfirm, isOpen, message, title, confirm, cancel } = useConfirm();

const availableIcons = [
    'truck',
    'home',
    'cake',
    'paper-airplane',
    'location-marker',
    'cash',
    'lightning-bolt',
    'dots-horizontal'
];

const suggestedColors = [
    '#EF4444', '#F59E0B', '#10B981', '#3B82F6',
    '#6366F1', '#8B5CF6', '#EC4899', '#06B6D4',
];

const submit = () => {
    form.put(route('categories.update', props.category.id));
};

const deleteCategory = () => {
    if (props.category.expenses_count > 0) {
        showConfirm({
            title: 'No se puede eliminar',
            message: `No se puede eliminar esta categoría porque tiene ${props.category.expenses_count} gastos asociados. Primero debes reasignar o eliminar estos gastos.`,
            onConfirm: () => {
                // Do nothing, just close the modal
            }
        });
        return;
    }

    showConfirm({
        title: 'Eliminar Categoría',
        message: `¿Estás seguro de que deseas eliminar la categoría "${props.category.nombre}"? Esta acción no se puede deshacer.`,
        onConfirm: () => {
            router.delete(route('categories.destroy', props.category.id));
        }
    });
};
</script>

<template>
    <AuthenticatedLayout>
        <!-- Back button and delete -->
        <div class="flex items-center justify-between mb-6">
            <Button
                variant="ghost"
                size="sm"
                @click="router.visit(route('categories.index'))"
            >
                <ArrowLeftIcon class="h-4 w-4" />
                Volver a Categorías
            </Button>

            <Button
                variant="danger"
                size="sm"
                @click="deleteCategory"
                :disabled="category.expenses_count > 0"
            >
                <TrashIcon class="h-4 w-4" />
                Eliminar Categoría
            </Button>
        </div>

        <!-- Header -->
        <div class="mb-8">
            <h1 class="text-3xl font-bold text-gray-900 dark:text-white">
                Editar Categoría
            </h1>
            <p class="mt-1 text-sm text-gray-600 dark:text-slate-400">
                Modifica la información de la categoría
            </p>
        </div>

        <!-- Alert if has expenses -->
        <Alert v-if="category.expenses_count > 0" type="warning" class="mb-6">
            <div class="flex items-start gap-2">
                <ExclamationTriangleIcon class="h-5 w-5 flex-shrink-0 mt-0.5" />
                <div class="text-sm">
                    <p class="font-medium mb-1">Esta categoría tiene {{ category.expenses_count }} gastos asociados</p>
                    <p>No puedes eliminar esta categoría mientras tenga gastos registrados. Reasigna los gastos a otra categoría primero.</p>
                </div>
            </div>
        </Alert>

        <form @submit.prevent="submit" class="max-w-3xl">
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- Form Section -->
                <div class="lg:col-span-2 space-y-6">
                    <!-- Basic Info -->
                    <Card>
                        <div class="card-header">
                            <h2 class="text-lg font-semibold text-gray-900 dark:text-white">
                                Información Básica
                            </h2>
                        </div>
                        <div class="card-body space-y-4">
                            <!-- Nombre -->
                            <div>
                                <InputLabel for="nombre" value="Nombre de la Categoría" required />
                                <Input
                                    id="nombre"
                                    v-model="form.nombre"
                                    type="text"
                                    placeholder="Ej: Transporte"
                                    :error="form.errors.nombre"
                                    required
                                />
                                <InputError :message="form.errors.nombre" />
                            </div>
                        </div>
                    </Card>

                    <!-- Visual Customization -->
                    <Card>
                        <div class="card-header">
                            <h2 class="text-lg font-semibold text-gray-900 dark:text-white">
                                Personalización Visual
                            </h2>
                        </div>
                        <div class="card-body space-y-6">
                            <!-- Color Picker -->
                            <div>
                                <InputLabel for="color" value="Color" required />
                                <div class="flex items-center gap-4">
                                    <input
                                        id="color"
                                        v-model="form.color"
                                        type="color"
                                        class="h-12 w-12 rounded-lg border-2 border-gray-300 dark:border-slate-600 cursor-pointer"
                                    />
                                    <Input
                                        v-model="form.color"
                                        type="text"
                                        placeholder="#6366F1"
                                        class="flex-1"
                                        :error="form.errors.color"
                                        readonly
                                    />
                                </div>
                                <InputError :message="form.errors.color" />

                                <!-- Suggested Colors -->
                                <div class="mt-3">
                                    <p class="text-xs text-gray-600 dark:text-slate-400 mb-2">Colores sugeridos:</p>
                                    <div class="flex flex-wrap gap-2">
                                        <button
                                            v-for="color in suggestedColors"
                                            :key="color"
                                            @click="form.color = color"
                                            type="button"
                                            class="w-8 h-8 rounded-lg border-2 hover:scale-110 transition-transform"
                                            :class="[
                                                form.color === color
                                                    ? 'border-gray-900 dark:border-white'
                                                    : 'border-gray-300 dark:border-slate-600'
                                            ]"
                                            :style="{ backgroundColor: color }"
                                        />
                                    </div>
                                </div>
                            </div>

                            <!-- Icon Selector -->
                            <div>
                                <InputLabel for="icono" value="Icono" required />
                                <div class="grid grid-cols-4 gap-3 mt-2">
                                    <button
                                        v-for="icon in availableIcons"
                                        :key="icon"
                                        @click="form.icono = icon"
                                        type="button"
                                        class="p-4 rounded-lg border-2 hover:scale-105 transition-all"
                                        :class="[
                                            form.icono === icon
                                                ? 'border-primary-600 dark:border-primary-400 bg-primary-50 dark:bg-primary-900/20'
                                                : 'border-gray-300 dark:border-slate-600 hover:border-gray-400 dark:hover:border-slate-500'
                                        ]"
                                    >
                                        <CategoryIcon
                                            :icon="icon"
                                            :color="form.color"
                                            size="lg"
                                        />
                                    </button>
                                </div>
                                <InputError :message="form.errors.icono" />
                            </div>
                        </div>
                    </Card>
                </div>

                <!-- Preview Section -->
                <div class="lg:col-span-1">
                    <Card class="sticky top-6">
                        <div class="card-header">
                            <h2 class="text-lg font-semibold text-gray-900 dark:text-white">
                                Vista Previa
                            </h2>
                        </div>
                        <div class="card-body">
                            <div class="text-center space-y-4">
                                <div
                                    class="w-24 h-24 mx-auto rounded-2xl flex items-center justify-center transition-all"
                                    :style="{ backgroundColor: form.color + '20' }"
                                >
                                    <CategoryIcon
                                        :icon="form.icono"
                                        :color="form.color"
                                        size="lg"
                                        class="!h-12 !w-12"
                                    />
                                </div>
                                <div>
                                    <h3 class="text-xl font-bold text-gray-900 dark:text-white">
                                        {{ form.nombre }}
                                    </h3>
                                    <p class="text-sm text-gray-500 dark:text-slate-400 mt-1 font-mono">
                                        {{ form.color }}
                                    </p>
                                </div>
                                <div class="pt-4 border-t border-gray-200 dark:border-slate-700">
                                    <div
                                        class="w-full h-3 rounded-full"
                                        :style="{ backgroundColor: form.color }"
                                    ></div>
                                </div>
                                <div class="pt-4 border-t border-gray-200 dark:border-slate-700">
                                    <p class="text-xs text-gray-500 dark:text-slate-400">
                                        {{ category.expenses_count }} gastos registrados
                                    </p>
                                </div>
                            </div>
                        </div>
                    </Card>
                </div>
            </div>

            <!-- Actions -->
            <div class="flex items-center justify-end gap-4 mt-6">
                <Button
                    variant="secondary"
                    type="button"
                    @click="router.visit(route('categories.index'))"
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
