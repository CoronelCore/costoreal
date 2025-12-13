<script setup>
import { ref, computed, inject } from 'vue';
import { router, useForm } from '@inertiajs/vue3';

const route = inject('route');
import { useTabs } from '@/Composables/useTabs';
import { useConfirm } from '@/Composables/useConfirm';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Button from '@/Components/UI/Button.vue';
import Badge from '@/Components/UI/Badge.vue';
import Card from '@/Components/UI/Card.vue';
import Input from '@/Components/UI/Input.vue';
import InputLabel from '@/Components/UI/InputLabel.vue';
import InputError from '@/Components/UI/InputError.vue';
import Modal from '@/Components/UI/Modal.vue';
import Alert from '@/Components/UI/Alert.vue';
import NumberDisplay from '@/Components/UI/NumberDisplay.vue';
import KpiCard from '@/Components/Domain/KpiCard.vue';
import CompanyIcon from '@/Components/Domain/CompanyIcon.vue';
import CategoryIcon from '@/Components/Domain/CategoryIcon.vue';
import {
    ArrowLeftIcon,
    PencilIcon,
    CheckCircleIcon,
    ArrowPathIcon,
    Cog6ToothIcon,
    ReceiptPercentIcon,
    ShoppingBagIcon,
    CurrencyDollarIcon,
    ChartBarIcon,
    PlusIcon,
    TrashIcon,
    PhotoIcon,
    XMarkIcon,
    ExclamationTriangleIcon,
} from '@heroicons/vue/24/outline';

const props = defineProps({
    trip: {
        type: Object,
        required: true
    },
    estadisticas: {
        type: Object,
        required: true
    },
    categories: {
        type: Array,
        required: true
    }
});

// Tabs management
const { activeTab, setTab } = useTabs('trip-show-tab', 'config');

// Expense form
const expenseForm = useForm({
    expense_category_id: '',
    concepto: '',
    moneda: props.trip.moneda_trabajo,
    monto: '',
    comprobante: null
});

const comprobanteInput = ref(null);

const submitExpense = () => {
    expenseForm.post(route('trips.expenses.store', props.trip.id), {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            expenseForm.reset();
            if (comprobanteInput.value) {
                comprobanteInput.value.value = '';
            }
        }
    });
};

// Expense editing
const editingExpenseId = ref(null);
const editForm = useForm({
    expense_category_id: '',
    concepto: '',
    moneda: '',
    monto: '',
    comprobante: null,
    eliminar_comprobante: false
});

const startEditingExpense = (expense) => {
    editingExpenseId.value = expense.id;
    editForm.expense_category_id = expense.expense_category_id || '';
    editForm.concepto = expense.concepto;
    editForm.moneda = expense.moneda;
    editForm.monto = expense.monto;
};

const cancelEditingExpense = () => {
    editingExpenseId.value = null;
    editForm.reset();
};

const updateExpense = (expense) => {
    editForm.put(route('trips.expenses.update', [props.trip.id, expense.id]), {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            editingExpenseId.value = null;
            editForm.reset();
        }
    });
};

// Expense deletion
const { show: showConfirm, isOpen, message, title, confirm, cancel } = useConfirm();

const deleteExpense = (expense) => {
    showConfirm({
        title: 'Eliminar Gasto',
        message: `¿Estás seguro de que deseas eliminar el gasto "${expense.concepto}"?`,
        onConfirm: () => {
            router.delete(route('trips.expenses.destroy', [props.trip.id, expense.id]), {
                preserveScroll: true
            });
        }
    });
};

// Image modal
const showImageModal = ref(false);
const selectedImage = ref(null);

const viewImage = (url) => {
    selectedImage.value = url;
    showImageModal.value = true;
};

const tabs = [
    { id: 'config', name: 'Configuración', icon: Cog6ToothIcon },
    { id: 'gastos', name: 'Gastos', icon: ReceiptPercentIcon, count: props.trip.expenses?.length || 0 },
    { id: 'productos', name: 'Productos', icon: ShoppingBagIcon, count: props.trip.products?.length || 0 },
    { id: 'precio-venta', name: 'Precio Venta', icon: CurrencyDollarIcon },
    { id: 'resumen', name: 'Resumen', icon: ChartBarIcon },
];

const getBadgeVariant = (estado) => {
    return {
        'activo': 'info',
        'finalizado': 'success',
        'archivado': 'gray'
    }[estado] || 'gray';
};

const canEdit = computed(() => props.trip.estado === 'activo');

const closeTrip = () => {
    if (confirm('¿Estás seguro de que deseas finalizar esta importación? No podrás agregar más gastos o productos.')) {
        router.patch(route('trips.close', props.trip.id));
    }
};

const reopenTrip = () => {
    if (confirm('¿Estás seguro de que deseas reabrir esta importación?')) {
        router.patch(route('trips.reopen', props.trip.id));
    }
};

const formatCurrency = (value) => {
    return new Intl.NumberFormat('es-CL', {
        minimumFractionDigits: 0,
        maximumFractionDigits: 0
    }).format(value);
};

const formatDate = (date) => {
    if (!date) return 'No especificada';
    return new Date(date).toLocaleDateString('es-CL', {
        year: 'numeric',
        month: 'long',
        day: 'numeric'
    });
};

// Product form
const productForm = useForm({
    nombre: '',
    moneda: props.trip.moneda_trabajo,
    cantidad: 1,
    costo_unitario: '',
    imagen: null
});

const imagenInput = ref(null);

const submitProduct = () => {
    productForm.post(route('trips.products.store', props.trip.id), {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            productForm.reset();
            if (imagenInput.value) {
                imagenInput.value.value = '';
            }
        }
    });
};

// Product editing
const editingProductId = ref(null);
const productEditForm = useForm({
    nombre: '',
    moneda: '',
    cantidad: 1,
    costo_unitario: '',
});

const startEditingProduct = (product) => {
    editingProductId.value = product.id;
    productEditForm.nombre = product.nombre;
    productEditForm.moneda = product.moneda;
    productEditForm.cantidad = product.cantidad;
    productEditForm.costo_unitario = product.costo_unitario;
};

const cancelEditingProduct = () => {
    editingProductId.value = null;
    productEditForm.reset();
};

const updateProduct = (product) => {
    productEditForm.put(route('trips.products.update', [props.trip.id, product.id]), {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            editingProductId.value = null;
            productEditForm.reset();
        }
    });
};

// Product deletion
const deleteProduct = (product) => {
    showConfirm({
        title: 'Eliminar Producto',
        message: `¿Estás seguro de que deseas eliminar el producto "${product.nombre}"? Esto recalculará el prorrateo de gastos.`,
        onConfirm: () => {
            router.delete(route('trips.products.destroy', [props.trip.id, product.id]), {
                preserveScroll: true
            });
        }
    });
};

// Sales price management
const preciosVentaTemp = ref({});

// Inicializar precios temporales
const initPreciosTemp = () => {
    const precios = {};
    props.trip.products?.forEach(p => {
        precios[p.id] = p.precio_venta || '';
    });
    preciosVentaTemp.value = precios;
};

// Llamar en onMounted si es necesario
if (props.trip.products) {
    initPreciosTemp();
}

const getPrecioVentaTemp = (productId) => {
    return preciosVentaTemp.value[productId] ?? '';
};

const updatePrecioVentaTemp = (productId, valor) => {
    preciosVentaTemp.value[productId] = valor;
};

const savePrecioVenta = (product) => {
    const precioVenta = preciosVentaTemp.value[product.id];
    if (precioVenta === '' || precioVenta === null) {
        return; // No guardar si está vacío
    }

    router.patch(
        route('trips.products.update-precio-venta', [props.trip.id, product.id]),
        { precio_venta: parseFloat(precioVenta) || 0 },
        {
            preserveScroll: true,
            onSuccess: () => {
                // El precio se actualizó correctamente
            }
        }
    );
};

// Sales calculations
const calcularMargen = (precioVenta, costoFinal) => {
    const precio = parseFloat(precioVenta) || 0;
    const costo = parseFloat(costoFinal) || 0;
    if (precio <= 0) return 0;
    return ((precio - costo) / precio) * 100;
};

const calcularGanancia = (precioVenta, costoFinal) => {
    const precio = parseFloat(precioVenta) || 0;
    const costo = parseFloat(costoFinal) || 0;
    return precio - costo;
};

const calcularGananciaTotal = (precioVenta, costoFinal, cantidad) => {
    const ganancia = calcularGanancia(precioVenta, costoFinal);
    const cant = parseFloat(cantidad) || 0;
    return ganancia * cant;
};

// Sales summary computed
const inversionTotal = computed(() => {
    return props.trip.products?.reduce((sum, p) => {
        const costo = parseFloat(p.costo_final_total_clp) || 0;
        return sum + costo;
    }, 0) || 0;
});

const ingresosEsperados = computed(() => {
    return props.trip.products?.reduce((sum, p) => {
        const precio = parseFloat(p.precio_venta) || 0;
        const cantidad = parseFloat(p.cantidad) || 0;
        return sum + (precio * cantidad);
    }, 0) || 0;
});

const gananciaTotal = computed(() => {
    const ingresos = parseFloat(ingresosEsperados.value) || 0;
    const inversion = parseFloat(inversionTotal.value) || 0;
    return ingresos - inversion;
});

const roiPorcentaje = computed(() => {
    const inversion = parseFloat(inversionTotal.value) || 0;
    const ganancia = parseFloat(gananciaTotal.value) || 0;
    if (inversion === 0) return 0;
    return (ganancia / inversion) * 100;
});

const hayPreciosVenta = computed(() => {
    return props.trip.products?.some(p => p.precio_venta && p.precio_venta > 0) || false;
});
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
        <div class="card mb-6">
            <div class="card-body">
                <div class="flex items-start justify-between mb-4">
                    <div class="flex-1">
                        <div class="flex items-center gap-3 mb-2">
                            <h1 class="text-3xl font-bold text-gray-900 dark:text-white">
                                {{ trip.nombre }}
                            </h1>
                            <Badge :variant="getBadgeVariant(trip.estado)">
                                {{ trip.estado }}
                            </Badge>
                        </div>
                        <p v-if="trip.descripcion" class="text-gray-600 dark:text-slate-400">
                            {{ trip.descripcion }}
                        </p>
                    </div>

                    <div class="flex items-center gap-2">
                        <Button
                            variant="secondary"
                            size="sm"
                            @click="router.visit(route('trips.edit', trip.id))"
                        >
                            <PencilIcon class="h-4 w-4" />
                            Editar
                        </Button>

                        <Button
                            v-if="trip.estado === 'activo'"
                            variant="primary"
                            size="sm"
                            @click="closeTrip"
                        >
                            <CheckCircleIcon class="h-4 w-4" />
                            Finalizar
                        </Button>

                        <Button
                            v-else-if="trip.estado === 'finalizado'"
                            variant="secondary"
                            size="sm"
                            @click="reopenTrip"
                        >
                            <ArrowPathIcon class="h-4 w-4" />
                            Reabrir
                        </Button>
                    </div>
                </div>

                <!-- Company info -->
                <div v-if="trip.company" class="flex items-center gap-3 p-3 bg-gray-50 dark:bg-slate-900 rounded-lg w-fit">
                    <CompanyIcon
                        :icon="trip.company?.icono"
                        :color="trip.company?.color"
                        size="md"
                    />
                    <div>
                        <p class="text-sm text-gray-600 dark:text-slate-400">Empresa</p>
                        <p class="font-semibold text-gray-900 dark:text-white">{{ trip.company.nombre }}</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tabs Navigation -->
        <div class="mb-6 border-b border-gray-200 dark:border-slate-700">
            <nav class="-mb-px flex space-x-4 overflow-x-auto scrollbar-thin">
                <button
                    v-for="tab in tabs"
                    :key="tab.id"
                    @click="setTab(tab.id)"
                    :class="[
                        activeTab === tab.id
                            ? 'border-primary-600 text-primary-600 dark:border-primary-400 dark:text-primary-400'
                            : 'border-transparent text-gray-600 hover:text-gray-900 hover:border-gray-300 dark:text-slate-400 dark:hover:text-slate-200',
                        'group inline-flex items-center gap-2 py-4 px-1 border-b-2 font-medium text-sm whitespace-nowrap transition-colors'
                    ]"
                    type="button"
                >
                    <component :is="tab.icon" class="h-5 w-5" />
                    <span>{{ tab.name }}</span>
                    <span
                        v-if="tab.count !== undefined"
                        :class="[
                            activeTab === tab.id
                                ? 'bg-primary-100 text-primary-600 dark:bg-primary-900/30 dark:text-primary-400'
                                : 'bg-gray-100 text-gray-600 dark:bg-slate-800 dark:text-slate-400',
                            'ml-1 py-0.5 px-2 rounded-full text-xs font-semibold'
                        ]"
                    >
                        {{ tab.count }}
                    </span>
                </button>
            </nav>
        </div>

        <!-- Tab Content -->
        <div class="animate-fade-in">
            <!-- Tab: Config -->
            <div v-if="activeTab === 'config'">
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    <!-- Moneda Base -->
                    <Card>
                        <div>
                            <p class="text-sm text-gray-600 dark:text-slate-400 mb-1">
                                Moneda Base
                            </p>
                            <p class="text-2xl font-bold text-gray-900 dark:text-white">
                                {{ trip.moneda_base }}
                            </p>
                            <p class="text-xs text-gray-500 dark:text-slate-500 mt-1">
                                Peso Chileno
                            </p>
                        </div>
                    </Card>

                    <!-- Moneda de Trabajo -->
                    <Card>
                        <div>
                            <p class="text-sm text-gray-600 dark:text-slate-400 mb-1">
                                Moneda de Trabajo
                            </p>
                            <p class="text-2xl font-bold text-gray-900 dark:text-white">
                                {{ trip.moneda_trabajo }}
                            </p>
                            <p class="text-xs text-gray-500 dark:text-slate-500 mt-1">
                                Moneda de los gastos
                            </p>
                        </div>
                    </Card>

                    <!-- Tipo de Cambio -->
                    <Card>
                        <div>
                            <p class="text-sm text-gray-600 dark:text-slate-400 mb-1">
                                Tipo de Cambio
                            </p>
                            <NumberDisplay
                                :value="trip.tipo_cambio"
                                size="md"
                            />
                            <p class="text-xs text-gray-500 dark:text-slate-500 mt-1">
                                {{ trip.moneda_trabajo }} a CLP
                            </p>
                        </div>
                    </Card>

                    <!-- Fecha Inicio -->
                    <Card>
                        <div>
                            <p class="text-sm text-gray-600 dark:text-slate-400 mb-1">
                                Fecha de Inicio
                            </p>
                            <p class="text-lg font-semibold text-gray-900 dark:text-white">
                                {{ formatDate(trip.fecha_inicio) }}
                            </p>
                        </div>
                    </Card>

                    <!-- Fecha Fin -->
                    <Card>
                        <div>
                            <p class="text-sm text-gray-600 dark:text-slate-400 mb-1">
                                Fecha de Fin
                            </p>
                            <p class="text-lg font-semibold text-gray-900 dark:text-white">
                                {{ formatDate(trip.fecha_fin) }}
                            </p>
                        </div>
                    </Card>

                    <!-- Estado -->
                    <Card>
                        <div>
                            <p class="text-sm text-gray-600 dark:text-slate-400 mb-1">
                                Estado
                            </p>
                            <Badge :variant="getBadgeVariant(trip.estado)" class="text-base">
                                {{ trip.estado }}
                            </Badge>
                        </div>
                    </Card>
                </div>

                <!-- Edit Link -->
                <div class="mt-6 text-center">
                    <Button
                        variant="secondary"
                        @click="router.visit(route('trips.edit', trip.id))"
                    >
                        <PencilIcon class="h-4 w-4" />
                        Editar Configuración
                    </Button>
                </div>
            </div>

            <!-- Tab: Gastos -->
            <div v-else-if="activeTab === 'gastos'" class="space-y-6">
                <!-- Formulario Agregar Gasto -->
                <Card v-if="canEdit">
                    <div class="card-header">
                        <h2 class="text-lg font-semibold text-gray-900 dark:text-white">
                            Agregar Gasto
                        </h2>
                    </div>
                    <div class="card-body">
                        <form @submit.prevent="submitExpense" class="space-y-4">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <!-- Categoría -->
                                <div>
                                    <InputLabel for="expense_category_id" value="Categoría" />
                                    <select
                                        id="expense_category_id"
                                        v-model="expenseForm.expense_category_id"
                                        class="select"
                                    >
                                        <option value="">Sin categoría</option>
                                        <option
                                            v-for="category in categories"
                                            :key="category.id"
                                            :value="category.id"
                                        >
                                            {{ category.nombre }}
                                        </option>
                                    </select>
                                    <InputError :message="expenseForm.errors.expense_category_id" />
                                </div>

                                <!-- Concepto -->
                                <div>
                                    <InputLabel for="concepto" value="Concepto" required />
                                    <Input
                                        id="concepto"
                                        v-model="expenseForm.concepto"
                                        type="text"
                                        placeholder="Descripción del gasto"
                                        required
                                        :error="expenseForm.errors.concepto"
                                    />
                                    <InputError :message="expenseForm.errors.concepto" />
                                </div>

                                <!-- Moneda -->
                                <div>
                                    <InputLabel for="moneda" value="Moneda" required />
                                    <select
                                        id="moneda"
                                        v-model="expenseForm.moneda"
                                        class="select"
                                        required
                                    >
                                        <option value="CLP">CLP</option>
                                        <option :value="trip.moneda_trabajo">{{ trip.moneda_trabajo }}</option>
                                    </select>
                                    <InputError :message="expenseForm.errors.moneda" />
                                </div>

                                <!-- Monto -->
                                <div>
                                    <InputLabel for="monto" value="Monto" required />
                                    <Input
                                        id="monto"
                                        v-model="expenseForm.monto"
                                        type="number"
                                        step="0.01"
                                        min="0"
                                        placeholder="0.00"
                                        required
                                        :error="expenseForm.errors.monto"
                                    />
                                    <InputError :message="expenseForm.errors.monto" />
                                </div>

                                <!-- Comprobante -->
                                <div class="md:col-span-2">
                                    <InputLabel for="comprobante" value="Comprobante (opcional)" />
                                    <input
                                        ref="comprobanteInput"
                                        id="comprobante"
                                        type="file"
                                        accept="image/*"
                                        class="input"
                                        @change="expenseForm.comprobante = $event.target.files[0]"
                                    />
                                    <InputError :message="expenseForm.errors.comprobante" />
                                </div>
                            </div>

                            <div class="flex justify-end">
                                <Button
                                    type="submit"
                                    variant="primary"
                                    :loading="expenseForm.processing"
                                    :disabled="expenseForm.processing"
                                >
                                    <PlusIcon class="h-4 w-4" />
                                    Agregar Gasto
                                </Button>
                            </div>
                        </form>
                    </div>
                </Card>

                <Alert v-else type="info">
                    Esta importación está finalizada. No se pueden agregar más gastos.
                </Alert>

                <!-- Tabla de Gastos -->
                <Card>
                    <div class="card-header">
                        <h2 class="text-lg font-semibold text-gray-900 dark:text-white">
                            Listado de Gastos ({{ trip.expenses?.length || 0 }})
                        </h2>
                    </div>

                    <div v-if="trip.expenses && trip.expenses.length > 0" class="overflow-x-auto">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Categoría</th>
                                    <th>Concepto</th>
                                    <th>Monto</th>
                                    <th>Monto CLP</th>
                                    <th>Comprobante</th>
                                    <th v-if="canEdit">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="expense in trip.expenses" :key="expense.id">
                                    <!-- Modo Vista -->
                                    <template v-if="editingExpenseId !== expense.id">
                                        <td>
                                            <div v-if="expense.category" class="flex items-center gap-2">
                                                <CategoryIcon
                                                    :icon="expense.category.icono"
                                                    :color="expense.category.color"
                                                    size="sm"
                                                />
                                                <span>{{ expense.category.nombre }}</span>
                                            </div>
                                            <span v-else class="text-gray-400">Sin categoría</span>
                                        </td>
                                        <td>{{ expense.concepto }}</td>
                                        <td>{{ expense.moneda }} ${{ formatCurrency(expense.monto) }}</td>
                                        <td>
                                            <span class="financial-number font-semibold">
                                                ${{ formatCurrency(expense.monto_clp) }}
                                            </span>
                                        </td>
                                        <td>
                                            <button
                                                v-if="expense.comprobante"
                                                @click="viewImage(`/storage/${expense.comprobante}`)"
                                                type="button"
                                                class="text-primary-600 hover:text-primary-700 dark:text-primary-400 flex items-center gap-1"
                                            >
                                                <PhotoIcon class="h-4 w-4" />
                                                Ver
                                            </button>
                                            <span v-else class="text-gray-400">—</span>
                                        </td>
                                        <td v-if="canEdit">
                                            <div class="flex items-center gap-2">
                                                <Button
                                                    variant="ghost"
                                                    size="sm"
                                                    @click="startEditingExpense(expense)"
                                                >
                                                    <PencilIcon class="h-4 w-4" />
                                                </Button>
                                                <Button
                                                    variant="danger"
                                                    size="sm"
                                                    @click="deleteExpense(expense)"
                                                >
                                                    <TrashIcon class="h-4 w-4" />
                                                </Button>
                                            </div>
                                        </td>
                                    </template>

                                    <!-- Modo Edición -->
                                    <template v-else>
                                        <td>
                                            <select v-model="editForm.expense_category_id" class="select text-sm">
                                                <option value="">Sin categoría</option>
                                                <option
                                                    v-for="category in categories"
                                                    :key="category.id"
                                                    :value="category.id"
                                                >
                                                    {{ category.nombre }}
                                                </option>
                                            </select>
                                        </td>
                                        <td>
                                            <Input
                                                v-model="editForm.concepto"
                                                type="text"
                                                class="text-sm"
                                            />
                                        </td>
                                        <td>
                                            <div class="flex gap-2">
                                                <select v-model="editForm.moneda" class="select text-sm w-20">
                                                    <option value="CLP">CLP</option>
                                                    <option :value="trip.moneda_trabajo">{{ trip.moneda_trabajo }}</option>
                                                </select>
                                                <Input
                                                    v-model="editForm.monto"
                                                    type="number"
                                                    step="0.01"
                                                    class="text-sm w-32"
                                                />
                                            </div>
                                        </td>
                                        <td>—</td>
                                        <td>—</td>
                                        <td>
                                            <div class="flex items-center gap-2">
                                                <Button
                                                    variant="primary"
                                                    size="sm"
                                                    @click="updateExpense(expense)"
                                                    :loading="editForm.processing"
                                                >
                                                    <CheckCircleIcon class="h-4 w-4" />
                                                </Button>
                                                <Button
                                                    variant="secondary"
                                                    size="sm"
                                                    @click="cancelEditingExpense"
                                                >
                                                    <XMarkIcon class="h-4 w-4" />
                                                </Button>
                                            </div>
                                        </td>
                                    </template>
                                </tr>
                            </tbody>
                            <tfoot>
                                <tr class="bg-gray-50 dark:bg-slate-900 font-semibold">
                                    <td colspan="3" class="text-right">Total:</td>
                                    <td>
                                        <span class="financial-number">
                                            ${{ formatCurrency(estadisticas.total_gastos_clp || 0) }}
                                        </span>
                                    </td>
                                    <td colspan="2"></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>

                    <div v-else class="card-body text-center py-12">
                        <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-gray-100 dark:bg-slate-800 mb-4">
                            <ReceiptPercentIcon class="h-8 w-8 text-gray-400" />
                        </div>
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-2">
                            No hay gastos registrados
                        </h3>
                        <p class="text-sm text-gray-600 dark:text-slate-400">
                            {{ canEdit ? 'Comienza agregando tu primer gasto arriba' : 'Esta importación no tiene gastos' }}
                        </p>
                    </div>
                </Card>
            </div>

            <!-- Tab: Productos -->
            <div v-else-if="activeTab === 'productos'" class="space-y-6">
                <!-- Formulario Agregar Producto -->
                <Card v-if="canEdit">
                    <div class="card-header">
                        <h2 class="text-lg font-semibold text-gray-900 dark:text-white">
                            Agregar Producto
                        </h2>
                    </div>
                    <div class="card-body">
                        <form @submit.prevent="submitProduct" class="space-y-4">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <!-- Nombre -->
                                <div>
                                    <InputLabel for="nombre_producto" value="Nombre del Producto" required />
                                    <Input
                                        id="nombre_producto"
                                        v-model="productForm.nombre"
                                        type="text"
                                        placeholder="Ej: Laptop Dell XPS"
                                        required
                                        :error="productForm.errors.nombre"
                                    />
                                    <InputError :message="productForm.errors.nombre" />
                                </div>

                                <!-- Moneda -->
                                <div>
                                    <InputLabel for="moneda_producto" value="Moneda" required />
                                    <select
                                        id="moneda_producto"
                                        v-model="productForm.moneda"
                                        class="select"
                                        required
                                    >
                                        <option value="CLP">CLP</option>
                                        <option :value="trip.moneda_trabajo">{{ trip.moneda_trabajo }}</option>
                                    </select>
                                    <InputError :message="productForm.errors.moneda" />
                                </div>

                                <!-- Cantidad -->
                                <div>
                                    <InputLabel for="cantidad" value="Cantidad" required />
                                    <Input
                                        id="cantidad"
                                        v-model="productForm.cantidad"
                                        type="number"
                                        step="1"
                                        min="1"
                                        placeholder="1"
                                        required
                                        :error="productForm.errors.cantidad"
                                    />
                                    <InputError :message="productForm.errors.cantidad" />
                                </div>

                                <!-- Costo Unitario -->
                                <div>
                                    <InputLabel for="costo_unitario" value="Costo Unitario" required />
                                    <Input
                                        id="costo_unitario"
                                        v-model="productForm.costo_unitario"
                                        type="number"
                                        step="0.01"
                                        min="0"
                                        placeholder="0.00"
                                        required
                                        :error="productForm.errors.costo_unitario"
                                    />
                                    <InputError :message="productForm.errors.costo_unitario" />
                                </div>

                                <!-- Imagen -->
                                <div class="md:col-span-2">
                                    <InputLabel for="imagen_producto" value="Imagen (opcional)" />
                                    <input
                                        ref="imagenInput"
                                        id="imagen_producto"
                                        type="file"
                                        accept="image/*"
                                        class="input"
                                        @change="productForm.imagen = $event.target.files[0]"
                                    />
                                    <InputError :message="productForm.errors.imagen" />
                                </div>
                            </div>

                            <div class="flex justify-end">
                                <Button
                                    type="submit"
                                    variant="primary"
                                    :loading="productForm.processing"
                                    :disabled="productForm.processing"
                                >
                                    <PlusIcon class="h-4 w-4" />
                                    Agregar Producto
                                </Button>
                            </div>
                        </form>
                    </div>
                </Card>

                <Alert v-else type="info">
                    Esta importación está finalizada. No se pueden agregar más productos.
                </Alert>

                <!-- Tabla de Productos -->
                <Card>
                    <div class="card-header">
                        <h2 class="text-lg font-semibold text-gray-900 dark:text-white">
                            Listado de Productos ({{ trip.products?.length || 0 }})
                        </h2>
                    </div>

                    <div v-if="trip.products && trip.products.length > 0" class="overflow-x-auto">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Imagen</th>
                                    <th>Nombre</th>
                                    <th>Cantidad</th>
                                    <th>Costo Unit.</th>
                                    <th>Subtotal CLP</th>
                                    <th>Gastos Prorrateados</th>
                                    <th>Costo Final Unit.</th>
                                    <th>Costo Final Total</th>
                                    <th v-if="canEdit">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="product in trip.products" :key="product.id">
                                    <!-- Modo Vista -->
                                    <template v-if="editingProductId !== product.id">
                                        <td>
                                            <button
                                                v-if="product.imagen"
                                                @click="viewImage(`/storage/${product.imagen}`)"
                                                type="button"
                                                class="w-12 h-12 rounded-lg overflow-hidden hover:opacity-80 transition"
                                            >
                                                <img
                                                    :src="`/storage/${product.imagen}`"
                                                    :alt="product.nombre"
                                                    class="w-full h-full object-cover"
                                                />
                                            </button>
                                            <div v-else class="w-12 h-12 rounded-lg bg-gray-100 dark:bg-slate-800 flex items-center justify-center">
                                                <PhotoIcon class="h-6 w-6 text-gray-400" />
                                            </div>
                                        </td>
                                        <td class="font-medium">{{ product.nombre }}</td>
                                        <td>{{ product.cantidad }}</td>
                                        <td>{{ product.moneda }} ${{ formatCurrency(product.costo_unitario) }}</td>
                                        <td>
                                            <span class="financial-number font-semibold">
                                                ${{ formatCurrency(product.subtotal_clp) }}
                                            </span>
                                        </td>
                                        <td class="text-orange-600 dark:text-orange-400">
                                            <span class="financial-number font-medium">
                                                ${{ formatCurrency(product.gastos_prorrateados_clp || 0) }}
                                            </span>
                                        </td>
                                        <td class="text-green-600 dark:text-green-400">
                                            <span class="financial-number font-semibold">
                                                ${{ formatCurrency(product.costo_final_unitario_clp || 0) }}
                                            </span>
                                        </td>
                                        <td class="text-green-700 dark:text-green-300">
                                            <span class="financial-number font-bold">
                                                ${{ formatCurrency(product.costo_final_total_clp || 0) }}
                                            </span>
                                        </td>
                                        <td v-if="canEdit">
                                            <div class="flex items-center gap-2">
                                                <Button
                                                    variant="ghost"
                                                    size="sm"
                                                    @click="startEditingProduct(product)"
                                                >
                                                    <PencilIcon class="h-4 w-4" />
                                                </Button>
                                                <Button
                                                    variant="danger"
                                                    size="sm"
                                                    @click="deleteProduct(product)"
                                                >
                                                    <TrashIcon class="h-4 w-4" />
                                                </Button>
                                            </div>
                                        </td>
                                    </template>

                                    <!-- Modo Edición -->
                                    <template v-else>
                                        <td>—</td>
                                        <td>
                                            <Input
                                                v-model="productEditForm.nombre"
                                                type="text"
                                                class="text-sm"
                                            />
                                        </td>
                                        <td>
                                            <Input
                                                v-model="productEditForm.cantidad"
                                                type="number"
                                                step="1"
                                                min="1"
                                                class="text-sm w-20"
                                            />
                                        </td>
                                        <td>
                                            <div class="flex gap-2">
                                                <select v-model="productEditForm.moneda" class="select text-sm w-20">
                                                    <option value="CLP">CLP</option>
                                                    <option :value="trip.moneda_trabajo">{{ trip.moneda_trabajo }}</option>
                                                </select>
                                                <Input
                                                    v-model="productEditForm.costo_unitario"
                                                    type="number"
                                                    step="0.01"
                                                    class="text-sm w-32"
                                                />
                                            </div>
                                        </td>
                                        <td colspan="4">—</td>
                                        <td>
                                            <div class="flex items-center gap-2">
                                                <Button
                                                    variant="primary"
                                                    size="sm"
                                                    @click="updateProduct(product)"
                                                    :loading="productEditForm.processing"
                                                >
                                                    <CheckCircleIcon class="h-4 w-4" />
                                                </Button>
                                                <Button
                                                    variant="secondary"
                                                    size="sm"
                                                    @click="cancelEditingProduct"
                                                >
                                                    <XMarkIcon class="h-4 w-4" />
                                                </Button>
                                            </div>
                                        </td>
                                    </template>
                                </tr>
                            </tbody>
                            <tfoot>
                                <tr class="bg-gray-50 dark:bg-slate-900 font-semibold">
                                    <td colspan="4" class="text-right">Totales:</td>
                                    <td>
                                        <span class="financial-number">
                                            ${{ formatCurrency(estadisticas.total_productos_clp || 0) }}
                                        </span>
                                    </td>
                                    <td class="text-orange-600 dark:text-orange-400">
                                        <span class="financial-number">
                                            ${{ formatCurrency(estadisticas.total_gastos_prorrateados_clp || 0) }}
                                        </span>
                                    </td>
                                    <td colspan="2" class="text-green-700 dark:text-green-300">
                                        <span class="financial-number">
                                            ${{ formatCurrency(estadisticas.costo_total_final_clp || 0) }}
                                        </span>
                                    </td>
                                    <td v-if="canEdit"></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>

                    <div v-else class="card-body text-center py-12">
                        <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-gray-100 dark:bg-slate-800 mb-4">
                            <ShoppingBagIcon class="h-8 w-8 text-gray-400" />
                        </div>
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-2">
                            No hay productos registrados
                        </h3>
                        <p class="text-sm text-gray-600 dark:text-slate-400">
                            {{ canEdit ? 'Comienza agregando tu primer producto arriba' : 'Esta importación no tiene productos' }}
                        </p>
                    </div>
                </Card>

                <!-- Info sobre Prorrateo -->
                <Alert v-if="trip.products && trip.products.length > 0" type="info">
                    <div class="flex items-start gap-2">
                        <ChartBarIcon class="h-5 w-5 flex-shrink-0 mt-0.5" />
                        <div class="text-sm">
                            <p class="font-medium mb-1">Sobre el Prorrateo de Gastos</p>
                            <p>Los gastos se distribuyen proporcionalmente entre los productos según su subtotal. El costo final incluye el costo base más los gastos prorrateados.</p>
                        </div>
                    </div>
                </Alert>
            </div>

            <!-- Tab: Precio Venta -->
            <div v-else-if="activeTab === 'precio-venta'" class="space-y-6">
                <template v-if="trip.products && trip.products.length > 0">
                    <!-- KPI Cards -->
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <!-- Inversión Total -->
                        <KpiCard
                            title="Inversión Total"
                            :value="`$${formatCurrency(inversionTotal)}`"
                            :icon="CurrencyDollarIcon"
                            variant="primary"
                        />

                        <!-- Ingresos Esperados -->
                        <KpiCard
                            title="Ingresos Esperados"
                            :value="`$${formatCurrency(ingresosEsperados)}`"
                            :icon="ChartBarIcon"
                            variant="success"
                        />

                        <!-- Ganancia/Pérdida -->
                        <KpiCard
                            :title="gananciaTotal >= 0 ? 'Ganancia' : 'Pérdida'"
                            :value="`$${formatCurrency(Math.abs(gananciaTotal))}`"
                            :icon="gananciaTotal >= 0 ? ArrowPathIcon : ExclamationTriangleIcon"
                            :variant="gananciaTotal >= 0 ? 'success' : 'danger'"
                        />
                    </div>

                    <!-- Tabla de Análisis de Precios -->
                    <Card>
                        <div class="card-header">
                            <h2 class="text-lg font-semibold text-gray-900 dark:text-white">
                                Análisis de Precios de Venta
                            </h2>
                        </div>

                        <div class="overflow-x-auto">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Producto</th>
                                        <th>Cantidad</th>
                                        <th>Costo Final Unit.</th>
                                        <th>Precio Venta Unit.</th>
                                        <th>Margen %</th>
                                        <th>Ganancia Unit.</th>
                                        <th>Ganancia Total</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="product in trip.products" :key="product.id">
                                        <td class="font-medium">{{ product.nombre }}</td>
                                        <td>{{ product.cantidad }}</td>
                                        <td>
                                            <NumberDisplay
                                                :value="product.costo_final_unitario_clp || 0"
                                                :currency="true"
                                                size="sm"
                                                class="font-semibold"
                                            />
                                        </td>
                                        <td>
                                            <Input
                                                :model-value="getPrecioVentaTemp(product.id)"
                                                @input="updatePrecioVentaTemp(product.id, $event.target.value)"
                                                @blur="savePrecioVenta(product)"
                                                type="number"
                                                step="0.01"
                                                min="0"
                                                placeholder="0.00"
                                                class="text-sm w-32"
                                                :disabled="!canEdit"
                                            />
                                        </td>
                                        <td>
                                            <span :class="[
                                                'font-medium',
                                                calcularMargen(getPrecioVentaTemp(product.id), product.costo_final_unitario_clp) >= 0
                                                    ? 'text-green-600 dark:text-green-400'
                                                    : 'text-red-600 dark:text-red-400'
                                            ]">
                                                {{ calcularMargen(getPrecioVentaTemp(product.id), product.costo_final_unitario_clp).toFixed(2) }}%
                                            </span>
                                        </td>
                                        <td :class="[
                                            calcularGanancia(getPrecioVentaTemp(product.id), product.costo_final_unitario_clp) >= 0
                                                ? 'text-green-600 dark:text-green-400'
                                                : 'text-red-600 dark:text-red-400'
                                        ]">
                                            <NumberDisplay
                                                :value="Math.abs(calcularGanancia(getPrecioVentaTemp(product.id), product.costo_final_unitario_clp))"
                                                :currency="true"
                                                size="sm"
                                                class="font-semibold"
                                            />
                                        </td>
                                        <td :class="[
                                            calcularGananciaTotal(getPrecioVentaTemp(product.id), product.costo_final_unitario_clp, product.cantidad) >= 0
                                                ? 'text-green-700 dark:text-green-300'
                                                : 'text-red-700 dark:text-red-300'
                                        ]">
                                            <NumberDisplay
                                                :value="Math.abs(calcularGananciaTotal(getPrecioVentaTemp(product.id), product.costo_final_unitario_clp, product.cantidad))"
                                                :currency="true"
                                                size="sm"
                                                class="font-bold"
                                            />
                                        </td>
                                    </tr>
                                </tbody>
                                <tfoot>
                                    <tr class="bg-gray-50 dark:bg-slate-900 font-semibold">
                                        <td colspan="2" class="text-right">Totales:</td>
                                        <td>
                                            <NumberDisplay
                                                :value="inversionTotal"
                                                :currency="true"
                                                size="sm"
                                            />
                                        </td>
                                        <td colspan="2"></td>
                                        <td></td>
                                        <td :class="[
                                            gananciaTotal >= 0
                                                ? 'text-green-700 dark:text-green-300'
                                                : 'text-red-700 dark:text-red-300'
                                        ]">
                                            <NumberDisplay
                                                :value="Math.abs(gananciaTotal)"
                                                :currency="true"
                                                size="sm"
                                            />
                                        </td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </Card>

                    <!-- Info sobre Precios -->
                    <Alert v-if="!hayPreciosVenta" type="warning">
                        <div class="flex items-start gap-2">
                            <CurrencyDollarIcon class="h-5 w-5 flex-shrink-0 mt-0.5" />
                            <div class="text-sm">
                                <p class="font-medium mb-1">Define Precios de Venta</p>
                                <p>Ingresa los precios de venta unitarios para calcular automáticamente márgenes, ganancias y ROI de tu importación.</p>
                            </div>
                        </div>
                    </Alert>

                    <Alert v-else type="info">
                        <div class="flex items-start gap-2">
                            <ChartBarIcon class="h-5 w-5 flex-shrink-0 mt-0.5" />
                            <div class="text-sm">
                                <p class="font-medium mb-1">Sobre el Análisis de Ventas</p>
                                <p>El margen se calcula como: (Precio Venta - Costo) / Precio Venta × 100. Un margen positivo indica ganancia, negativo indica pérdida.</p>
                            </div>
                        </div>
                    </Alert>
                </template>

                <Alert v-else type="warning">
                    <div class="text-center py-4">
                        <ShoppingBagIcon class="h-12 w-12 text-gray-400 mx-auto mb-3" />
                        <p class="font-medium mb-1">No hay productos para analizar</p>
                        <p class="text-sm">Agrega productos en la pestaña de Productos para poder definir precios de venta y calcular márgenes.</p>
                    </div>
                </Alert>
            </div>

            <!-- Tab: Resumen -->
            <div v-else-if="activeTab === 'resumen'" class="space-y-6">
                <!-- KPI Cards -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                    <!-- Total Gastos -->
                    <KpiCard
                        title="Total Gastos"
                        :value="`$${formatCurrency(estadisticas.total_gastos || 0)}`"
                        :icon="ReceiptPercentIcon"
                        variant="danger"
                    />

                    <!-- Total Productos -->
                    <KpiCard
                        title="Total Productos"
                        :value="`$${formatCurrency(estadisticas.total_productos || 0)}`"
                        :icon="ShoppingBagIcon"
                        variant="primary"
                    />

                    <!-- Gastos Prorrateados -->
                    <KpiCard
                        title="Gastos Distribuidos"
                        :value="`$${formatCurrency(estadisticas.gastos_prorrateados || 0)}`"
                        :icon="ChartBarIcon"
                        variant="warning"
                    />

                    <!-- Costo Total -->
                    <KpiCard
                        title="Costo Total Final"
                        :value="`$${formatCurrency(inversionTotal)}`"
                        :icon="CurrencyDollarIcon"
                        variant="success"
                    />
                </div>

                <!-- Detalle de Prorrateo por Producto -->
                <Card v-if="trip.products && trip.products.length > 0">
                    <div class="card-header">
                        <h2 class="text-lg font-semibold text-gray-900 dark:text-white">
                            Detalle de Distribución de Gastos
                        </h2>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Producto</th>
                                    <th>Cantidad</th>
                                    <th>Subtotal</th>
                                    <th>% Participación</th>
                                    <th>Gastos Asignados</th>
                                    <th>Costo Final</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="product in trip.products" :key="product.id">
                                    <td class="font-medium">{{ product.nombre }}</td>
                                    <td>{{ product.cantidad }}</td>
                                    <td>
                                        <span class="financial-number">
                                            ${{ formatCurrency(product.subtotal_clp || 0) }}
                                        </span>
                                    </td>
                                    <td>
                                        <div class="flex items-center gap-2">
                                            <div class="flex-1 bg-gray-200 dark:bg-slate-700 rounded-full h-2 max-w-[100px]">
                                                <div
                                                    class="bg-primary-600 dark:bg-primary-400 h-2 rounded-full"
                                                    :style="{ width: `${(product.subtotal_clp / estadisticas.total_productos * 100) || 0}%` }"
                                                ></div>
                                            </div>
                                            <span class="text-sm font-medium">
                                                {{ ((product.subtotal_clp / estadisticas.total_productos * 100) || 0).toFixed(2) }}%
                                            </span>
                                        </div>
                                    </td>
                                    <td class="text-orange-600 dark:text-orange-400">
                                        <span class="financial-number font-medium">
                                            ${{ formatCurrency(product.gastos_prorrateados_clp || 0) }}
                                        </span>
                                    </td>
                                    <td class="text-green-700 dark:text-green-300">
                                        <span class="financial-number font-bold">
                                            ${{ formatCurrency(product.costo_final_total_clp || 0) }}
                                        </span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </Card>

                <!-- Análisis de Rentabilidad (si hay precios de venta) -->
                <Card v-if="hayPreciosVenta">
                    <div class="card-header">
                        <h2 class="text-lg font-semibold text-gray-900 dark:text-white flex items-center gap-2">
                            <ChartBarIcon class="h-5 w-5" />
                            Análisis de Rentabilidad
                        </h2>
                    </div>

                    <div class="card-body">
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                            <!-- Inversión -->
                            <div class="text-center">
                                <p class="text-sm text-gray-600 dark:text-slate-400 mb-2">Inversión Total</p>
                                <div class="text-blue-600 dark:text-blue-400">
                                    <NumberDisplay
                                        :value="inversionTotal"
                                        :currency="true"
                                        size="lg"
                                    />
                                </div>
                            </div>

                            <!-- Ingresos -->
                            <div class="text-center">
                                <p class="text-sm text-gray-600 dark:text-slate-400 mb-2">Ingresos Esperados</p>
                                <div class="text-green-600 dark:text-green-400">
                                    <NumberDisplay
                                        :value="ingresosEsperados"
                                        :currency="true"
                                        size="lg"
                                    />
                                </div>
                            </div>

                            <!-- Ganancia -->
                            <div class="text-center">
                                <p class="text-sm text-gray-600 dark:text-slate-400 mb-2">
                                    {{ gananciaTotal >= 0 ? 'Ganancia' : 'Pérdida' }}
                                </p>
                                <div :class="gananciaTotal >= 0 ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400'">
                                    <NumberDisplay
                                        :value="Math.abs(gananciaTotal)"
                                        :currency="true"
                                        size="lg"
                                    />
                                </div>
                            </div>

                            <!-- ROI -->
                            <div class="text-center">
                                <p class="text-sm text-gray-600 dark:text-slate-400 mb-2">ROI</p>
                                <p :class="[
                                    'text-4xl font-bold financial-number',
                                    roiPorcentaje >= 0 ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400'
                                ]">
                                    {{ roiPorcentaje.toFixed(2) }}%
                                </p>
                            </div>
                        </div>

                        <div class="mt-6 p-4 bg-gradient-to-r from-primary-50 to-primary-100 dark:from-primary-900/20 dark:to-primary-800/20 rounded-lg">
                            <p class="text-center text-sm text-gray-700 dark:text-slate-300">
                                <span class="font-semibold">Conclusión:</span>
                                <template v-if="gananciaTotal >= 0">
                                    Si vendes todos los productos a los precios establecidos, obtendrás una ganancia de
                                    <NumberDisplay
                                        :value="gananciaTotal"
                                        :currency="true"
                                        size="sm"
                                        class="inline font-bold text-green-600 dark:text-green-400"
                                    />
                                    con un retorno de inversión del
                                    <span class="font-bold">{{ roiPorcentaje.toFixed(2) }}%</span>.
                                </template>
                                <template v-else>
                                    Los precios actuales generarían una pérdida de
                                    <NumberDisplay
                                        :value="Math.abs(gananciaTotal)"
                                        :currency="true"
                                        size="sm"
                                        class="inline font-bold text-red-600 dark:text-red-400"
                                    />.
                                    Considera ajustar los precios de venta.
                                </template>
                            </p>
                        </div>
                    </div>
                </Card>

                <!-- Información sobre el viaje -->
                <Card>
                    <div class="card-header">
                        <h2 class="text-lg font-semibold text-gray-900 dark:text-white">
                            Información del Viaje
                        </h2>
                    </div>

                    <div class="card-body">
                        <dl class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <dt class="text-sm text-gray-600 dark:text-slate-400">Nombre</dt>
                                <dd class="text-base font-semibold text-gray-900 dark:text-white">{{ trip.nombre }}</dd>
                            </div>

                            <div v-if="trip.company">
                                <dt class="text-sm text-gray-600 dark:text-slate-400">Empresa</dt>
                                <dd class="text-base font-semibold text-gray-900 dark:text-white flex items-center gap-2">
                                    <CompanyIcon :icon="trip.company?.icono" :color="trip.company?.color" size="sm" />
                                    {{ trip.company?.nombre }}
                                </dd>
                            </div>

                            <div>
                                <dt class="text-sm text-gray-600 dark:text-slate-400">Moneda de Trabajo</dt>
                                <dd class="text-base font-semibold text-gray-900 dark:text-white">{{ trip.moneda_trabajo }}</dd>
                            </div>

                            <div>
                                <dt class="text-sm text-gray-600 dark:text-slate-400">Tipo de Cambio</dt>
                                <dd class="text-base font-semibold text-gray-900 dark:text-white">
                                    ${{ formatCurrency(trip.tipo_cambio) }} CLP
                                </dd>
                            </div>

                            <div>
                                <dt class="text-sm text-gray-600 dark:text-slate-400">Fecha de Inicio</dt>
                                <dd class="text-base font-semibold text-gray-900 dark:text-white">{{ formatDate(trip.fecha_inicio) }}</dd>
                            </div>

                            <div>
                                <dt class="text-sm text-gray-600 dark:text-slate-400">Fecha de Fin</dt>
                                <dd class="text-base font-semibold text-gray-900 dark:text-white">{{ formatDate(trip.fecha_fin) }}</dd>
                            </div>

                            <div>
                                <dt class="text-sm text-gray-600 dark:text-slate-400">Estado</dt>
                                <dd>
                                    <Badge :variant="getBadgeVariant(trip.estado)">{{ trip.estado }}</Badge>
                                </dd>
                            </div>

                            <div v-if="trip.descripcion" class="md:col-span-2">
                                <dt class="text-sm text-gray-600 dark:text-slate-400">Descripción</dt>
                                <dd class="text-base text-gray-900 dark:text-white">{{ trip.descripcion }}</dd>
                            </div>
                        </dl>
                    </div>
                </Card>
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

        <!-- Image Viewer Modal -->
        <Modal :show="showImageModal" @close="showImageModal = false" maxWidth="4xl">
            <template #header>
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                    Vista Previa
                </h3>
            </template>

            <div class="flex items-center justify-center bg-gray-100 dark:bg-slate-900 rounded-lg p-4">
                <img
                    v-if="selectedImage"
                    :src="selectedImage"
                    alt="Vista previa"
                    class="max-w-full max-h-[70vh] object-contain rounded-lg"
                />
            </div>

            <template #footer>
                <div class="flex justify-end">
                    <Button variant="secondary" @click="showImageModal = false">
                        Cerrar
                    </Button>
                </div>
            </template>
        </Modal>
    </AuthenticatedLayout>
</template>
