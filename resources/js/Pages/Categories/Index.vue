<script setup>
import { inject } from 'vue';
import { router } from '@inertiajs/vue3';
import { useConfirm } from '@/Composables/useConfirm';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Button from '@/Components/UI/Button.vue';
import Card from '@/Components/UI/Card.vue';
import Badge from '@/Components/UI/Badge.vue';
import Modal from '@/Components/UI/Modal.vue';
import Alert from '@/Components/UI/Alert.vue';
import CategoryIcon from '@/Components/Domain/CategoryIcon.vue';
import { PlusIcon, PencilIcon, TrashIcon, TagIcon, ExclamationTriangleIcon } from '@heroicons/vue/24/outline';

// Ziggy route helper
const route = inject('route');

const props = defineProps({
    categories: {
        type: Array,
        required: true
    }
});

const { show: showConfirm, isOpen, message, title, confirm, cancel } = useConfirm();

const deleteCategory = (category) => {
    if (category.expenses_count > 0) {
        showConfirm({
            title: 'No se puede eliminar',
            message: `No se puede eliminar la categoría "${category.nombre}" porque tiene ${category.expenses_count} gastos asociados. Primero debes reasignar o eliminar estos gastos.`,
            onConfirm: () => {
                // Do nothing, just close the modal
            }
        });
        return;
    }

    showConfirm({
        title: 'Eliminar Categoría',
        message: `¿Estás seguro de que deseas eliminar la categoría "${category.nombre}"? Esta acción no se puede deshacer.`,
        onConfirm: () => {
            router.delete(route('categories.destroy', category.id));
        }
    });
};
</script>

<template>
    <AuthenticatedLayout>
        <!-- Header -->
        <div class="mb-8 flex items-start justify-between">
            <div>
                <h1 class="text-3xl font-bold text-gray-900 dark:text-white">
                    Categorías de Gastos
                </h1>
                <p class="mt-1 text-sm text-gray-600 dark:text-slate-400">
                    Gestiona las categorías para organizar tus gastos
                </p>
            </div>

            <Button
                variant="primary"
                @click="router.visit(route('categories.create'))"
            >
                <PlusIcon class="h-5 w-5" />
                Nueva Categoría
            </Button>
        </div>

        <!-- Categories Grid -->
        <div v-if="categories && categories.length > 0" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
            <div
                v-for="category in categories"
                :key="category.id"
                class="card card-hover animate-fade-in"
            >
                <div class="card-body">
                    <!-- Icon and Name -->
                    <div class="flex items-start gap-4 mb-4">
                        <div
                            class="flex-shrink-0 w-14 h-14 rounded-lg flex items-center justify-center transition-transform hover:scale-110"
                            :style="{ backgroundColor: category.color + '20' }"
                        >
                            <CategoryIcon
                                :icon="category.icono"
                                :color="category.color"
                                size="lg"
                            />
                        </div>
                        <div class="flex-1 min-w-0">
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-white truncate">
                                {{ category.nombre }}
                            </h3>
                            <p class="text-sm text-gray-600 dark:text-slate-400">
                                {{ category.expenses_count }} gastos
                            </p>
                        </div>
                    </div>

                    <!-- Color Preview -->
                    <div class="mb-4">
                        <div class="flex items-center gap-2">
                            <div
                                class="flex-1 h-3 rounded-full"
                                :style="{ backgroundColor: category.color }"
                            ></div>
                            <span class="text-xs text-gray-500 dark:text-slate-400 font-mono">
                                {{ category.color }}
                            </span>
                        </div>
                    </div>

                    <!-- Warning if has expenses -->
                    <div v-if="category.expenses_count > 0" class="mb-4">
                        <div class="flex items-center gap-2 px-3 py-2 rounded-lg bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800">
                            <ExclamationTriangleIcon class="h-4 w-4 text-amber-600 dark:text-amber-400 flex-shrink-0" />
                            <p class="text-xs text-amber-800 dark:text-amber-200">
                                Tiene gastos asociados
                            </p>
                        </div>
                    </div>

                    <!-- Actions -->
                    <div class="flex items-center gap-2 pt-4 border-t border-gray-200 dark:border-slate-700">
                        <Button
                            variant="ghost"
                            size="sm"
                            class="flex-1"
                            @click="router.visit(route('categories.edit', category.id))"
                        >
                            <PencilIcon class="h-4 w-4" />
                            Editar
                        </Button>
                        <Button
                            variant="danger"
                            size="sm"
                            @click="deleteCategory(category)"
                            :disabled="category.expenses_count > 0"
                        >
                            <TrashIcon class="h-4 w-4" />
                        </Button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Empty State -->
        <div v-else class="card">
            <div class="card-body text-center py-12">
                <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-gray-100 dark:bg-slate-800 mb-4">
                    <TagIcon class="h-8 w-8 text-gray-400" />
                </div>
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-2">
                    No hay categorías registradas
                </h3>
                <p class="text-sm text-gray-600 dark:text-slate-400 mb-6">
                    Crea categorías para organizar mejor tus gastos y tener un mejor control de tus importaciones
                </p>
                <Button
                    variant="primary"
                    @click="router.visit(route('categories.create'))"
                >
                    <PlusIcon class="h-5 w-5" />
                    Crear Primera Categoría
                </Button>
            </div>
        </div>

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
