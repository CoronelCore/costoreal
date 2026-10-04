<script setup>
import { inject } from 'vue';
import { router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Button from '@/Components/UI/Button.vue';
import Badge from '@/Components/UI/Badge.vue';
import CompanyIcon from '@/Components/Domain/CompanyIcon.vue';
import { useConfirm } from '@/Composables/useConfirm';
import { PencilIcon, TrashIcon, EyeIcon, PlusIcon } from '@heroicons/vue/24/outline';

const props = defineProps({
    companies: {
        type: Array,
        required: true
    }
});

const { show: showConfirm, isOpen, message, title, confirm, cancel } = useConfirm();

const deleteCompany = (company) => {
    showConfirm({
        title: 'Eliminar Empresa',
        message: `¿Estás seguro de que deseas eliminar la empresa "${company.nombre}"? Esta acción no se puede deshacer.`,
        onConfirm: () => {
            router.delete(route('companies.destroy', company.id), {
                preserveScroll: true,
            });
        }
    });
};
</script>

<template>
    <AuthenticatedLayout>
        <!-- Header -->
        <div class="flex items-center justify-between mb-8">
            <div>
                <h1 class="text-3xl font-bold text-gray-900 dark:text-white">
                    Empresas
                </h1>
                <p class="mt-1 text-sm text-gray-600 dark:text-slate-400">
                    Gestiona las empresas asociadas a tu cuenta
                </p>
            </div>

            <Button
                variant="primary"
                @click="router.visit(route('companies.create'))"
            >
                <PlusIcon class="h-5 w-5" />
                Nueva Empresa
            </Button>
        </div>

        <!-- Companies Grid -->
        <div v-if="companies.length > 0" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            <div
                v-for="(company, index) in companies"
                :key="company.id"
                class="card card-hover animate-fade-in stagger-item"
                :style="{ animationDelay: `${index * 50}ms` }"
            >
                <div class="card-body">
                    <!-- Company Icon and Name -->
                    <div class="flex items-start gap-4 mb-4">
                        <div class="relative shrink-0">
                            <div class="p-3 rounded-xl bg-linear-to-br from-gray-50 to-gray-100 dark:from-slate-700 dark:to-slate-800 shadow-xs">
                                <CompanyIcon
                                    :icon="company.icono"
                                    :color="company.color"
                                    size="lg"
                                />
                            </div>
                            <!-- Color dot indicator -->
                            <div
                                class="absolute -bottom-1 -right-1 w-4 h-4 rounded-full border-2 border-white dark:border-slate-800 shadow-xs"
                                :style="{ backgroundColor: company.color }"
                            />
                        </div>
                        <div class="flex-1 min-w-0">
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-white truncate">
                                {{ company.nombre }}
                            </h3>
                            <p v-if="company.rut" class="text-sm text-gray-600 dark:text-slate-400">
                                RUT: {{ company.rut }}
                            </p>
                        </div>
                        <Badge :variant="company.activa ? 'success' : 'gray'">
                            {{ company.activa ? 'Activa' : 'Inactiva' }}
                        </Badge>
                    </div>

                    <!-- Description -->
                    <p
                        v-if="company.descripcion"
                        class="text-sm text-gray-600 dark:text-slate-400 mb-4 line-clamp-2"
                    >
                        {{ company.descripcion }}
                    </p>

                    <!-- Stats -->
                    <div class="flex items-center gap-2 mb-4">
                        <div class="flex items-center gap-2 px-3 py-2 rounded-lg bg-primary-50 dark:bg-primary-900/20 text-primary-700 dark:text-primary-300">
                            <TruckIcon class="h-4 w-4" />
                            <span class="text-sm font-semibold">{{ company.trips_count || 0 }}</span>
                            <Badge variant="primary" size="sm">
                                importaciones
                            </Badge>
                        </div>
                    </div>

                    <!-- Actions -->
                    <div class="flex items-center gap-2 pt-4 border-t border-gray-200 dark:border-slate-700">
                        <Button
                            variant="ghost"
                            size="sm"
                            @click="router.visit(route('companies.show', company.id))"
                            class="flex-1"
                        >
                            <EyeIcon class="h-4 w-4" />
                            Ver
                        </Button>
                        <Button
                            variant="ghost"
                            size="sm"
                            @click="router.visit(route('companies.edit', company.id))"
                            class="flex-1"
                        >
                            <PencilIcon class="h-4 w-4" />
                            Editar
                        </Button>
                        <Button
                            variant="danger"
                            size="sm"
                            @click="deleteCompany(company)"
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
                    <BuildingOfficeIcon class="h-8 w-8 text-gray-400" />
                </div>
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-2">
                    No hay empresas registradas
                </h3>
                <p class="text-sm text-gray-600 dark:text-slate-400 mb-6">
                    Comienza creando tu primera empresa para gestionar tus importaciones
                </p>
                <Button
                    variant="primary"
                    @click="router.visit(route('companies.create'))"
                >
                    <PlusIcon class="h-5 w-5" />
                    Crear Primera Empresa
                </Button>
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

<script>
import { TruckIcon, BuildingOfficeIcon } from '@heroicons/vue/24/outline';
import Modal from '@/Components/UI/Modal.vue';

// Ziggy route helper
const route = inject('route');

export default {
    components: {
        TruckIcon,
        BuildingOfficeIcon,
        Modal,
    }
}
</script>
