<script setup>
import { ref, inject } from 'vue';
import { useForm, usePage } from '@inertiajs/vue3';
import { useConfirm } from '@/Composables/useConfirm';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Button from '@/Components/UI/Button.vue';
import Input from '@/Components/UI/Input.vue';
import InputLabel from '@/Components/UI/InputLabel.vue';
import InputError from '@/Components/UI/InputError.vue';
import Card from '@/Components/UI/Card.vue';
import Modal from '@/Components/UI/Modal.vue';
import Alert from '@/Components/UI/Alert.vue';
import {
    UserIcon,
    EnvelopeIcon,
    LockClosedIcon,
    ExclamationTriangleIcon,
    CheckCircleIcon,
    EyeIcon,
    EyeSlashIcon
} from '@heroicons/vue/24/outline';

// Ziggy route helper
const route = inject('route');

defineProps({
    mustVerifyEmail: {
        type: Boolean,
        default: false
    },
    status: String,
});

const page = usePage();
const user = page.props.auth.user;

// Profile form
const profileForm = useForm({
    name: user.name,
    email: user.email,
});

const updateProfile = () => {
    profileForm.patch(route('profile.update'), {
        preserveScroll: true,
        onSuccess: () => {
            profileForm.clearErrors();
        }
    });
};

// Password form
const passwordForm = useForm({
    current_password: '',
    password: '',
    password_confirmation: '',
});

const updatePassword = () => {
    passwordForm.put(route('password.update'), {
        preserveScroll: true,
        onSuccess: () => {
            passwordForm.reset();
        },
        onError: () => {
            passwordForm.reset('current_password', 'password', 'password_confirmation');
        }
    });
};

// Password visibility toggles
const showCurrentPassword = ref(false);
const showNewPassword = ref(false);
const showConfirmPassword = ref(false);

// Delete account
const { show: showConfirm, isOpen, message, title, confirm, cancel } = useConfirm();
const deleteAccountPassword = ref('');
const showDeleteModal = ref(false);
const deleteError = ref('');
const showDeletePassword = ref(false);

const confirmDeleteAccount = () => {
    showConfirm({
        title: 'Eliminar Cuenta',
        message: '¿Estás seguro de que deseas eliminar tu cuenta? Esta acción no se puede deshacer y perderás todos tus datos permanentemente.',
        onConfirm: () => {
            showDeleteModal.value = true;
        }
    });
};

const deleteAccount = () => {
    deleteError.value = '';

    fetch(route('profile.destroy'), {
        method: 'DELETE',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': page.props.csrf_token || document.querySelector('meta[name="csrf-token"]')?.content,
        },
        body: JSON.stringify({
            password: deleteAccountPassword.value
        })
    }).then(response => {
        if (response.ok) {
            window.location.href = '/';
        } else {
            return response.json();
        }
    }).then(data => {
        if (data?.errors?.password) {
            deleteError.value = data.errors.password[0];
        }
    });
};
</script>

<template>
    <AuthenticatedLayout>
        <!-- Header -->
        <div class="mb-8">
            <h1 class="text-3xl font-bold text-gray-900 dark:text-white">
                Perfil de Usuario
            </h1>
            <p class="mt-1 text-sm text-gray-600 dark:text-slate-400">
                Gestiona la información de tu cuenta y preferencias
            </p>
        </div>

        <div class="max-w-4xl space-y-6">
            <!-- Success Messages -->
            <Alert v-if="status === 'profile-updated'" type="success">
                <div class="flex items-center gap-2">
                    <CheckCircleIcon class="h-5 w-5" />
                    Perfil actualizado exitosamente
                </div>
            </Alert>

            <Alert v-if="status === 'password-updated'" type="success">
                <div class="flex items-center gap-2">
                    <CheckCircleIcon class="h-5 w-5" />
                    Contraseña actualizada exitosamente
                </div>
            </Alert>

            <!-- Update Profile Information -->
            <Card>
                <div class="card-header">
                    <div class="flex items-center gap-2">
                        <UserIcon class="h-6 w-6 text-gray-600 dark:text-slate-400" />
                        <h2 class="text-lg font-semibold text-gray-900 dark:text-white">
                            Información del Perfil
                        </h2>
                    </div>
                    <p class="text-sm text-gray-600 dark:text-slate-400 mt-1">
                        Actualiza tu nombre y dirección de correo electrónico
                    </p>
                </div>

                <div class="card-body">
                    <form @submit.prevent="updateProfile" class="space-y-4">
                        <!-- Name -->
                        <div>
                            <InputLabel for="name" value="Nombre" required />
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                    <UserIcon class="h-5 w-5 text-gray-400" />
                                </div>
                                <Input
                                    id="name"
                                    v-model="profileForm.name"
                                    type="text"
                                    required
                                    :error="profileForm.errors.name"
                                    class="pl-10"
                                />
                            </div>
                            <InputError :message="profileForm.errors.name" />
                        </div>

                        <!-- Email -->
                        <div>
                            <InputLabel for="email" value="Email" required />
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                    <EnvelopeIcon class="h-5 w-5 text-gray-400" />
                                </div>
                                <Input
                                    id="email"
                                    v-model="profileForm.email"
                                    type="email"
                                    required
                                    :error="profileForm.errors.email"
                                    class="pl-10"
                                />
                            </div>
                            <InputError :message="profileForm.errors.email" />
                        </div>

                        <!-- Email Verification Notice -->
                        <Alert v-if="mustVerifyEmail && user.email_verified_at === null" type="warning">
                            <div class="text-sm">
                                <p class="font-medium mb-1">Tu dirección de correo electrónico no está verificada.</p>
                                <Button variant="ghost" size="sm">
                                    Reenviar correo de verificación
                                </Button>
                            </div>
                        </Alert>

                        <!-- Actions -->
                        <div class="flex items-center justify-end">
                            <Button
                                type="submit"
                                variant="primary"
                                :loading="profileForm.processing"
                                :disabled="profileForm.processing"
                            >
                                Guardar Cambios
                            </Button>
                        </div>
                    </form>
                </div>
            </Card>

            <!-- Update Password -->
            <Card>
                <div class="card-header">
                    <div class="flex items-center gap-2">
                        <LockClosedIcon class="h-6 w-6 text-gray-600 dark:text-slate-400" />
                        <h2 class="text-lg font-semibold text-gray-900 dark:text-white">
                            Actualizar Contraseña
                        </h2>
                    </div>
                    <p class="text-sm text-gray-600 dark:text-slate-400 mt-1">
                        Asegúrate de usar una contraseña larga y segura
                    </p>
                </div>

                <div class="card-body">
                    <form @submit.prevent="updatePassword" class="space-y-4">
                        <!-- Current Password -->
                        <div>
                            <InputLabel for="current_password" value="Contraseña Actual" required />
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                    <LockClosedIcon class="h-5 w-5 text-gray-400" />
                                </div>
                                <Input
                                    id="current_password"
                                    v-model="passwordForm.current_password"
                                    :type="showCurrentPassword ? 'text' : 'password'"
                                    required
                                    :error="passwordForm.errors.current_password"
                                    autocomplete="current-password"
                                    class="pl-10 pr-10"
                                />
                                <button
                                    type="button"
                                    @click="showCurrentPassword = !showCurrentPassword"
                                    class="absolute inset-y-0 right-0 pr-3 flex items-center text-gray-400 hover:text-gray-600 dark:hover:text-slate-300"
                                >
                                    <EyeIcon v-if="!showCurrentPassword" class="h-5 w-5" />
                                    <EyeSlashIcon v-else class="h-5 w-5" />
                                </button>
                            </div>
                            <InputError :message="passwordForm.errors.current_password" />
                        </div>

                        <!-- New Password -->
                        <div>
                            <InputLabel for="password" value="Nueva Contraseña" required />
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                    <LockClosedIcon class="h-5 w-5 text-gray-400" />
                                </div>
                                <Input
                                    id="password"
                                    v-model="passwordForm.password"
                                    :type="showNewPassword ? 'text' : 'password'"
                                    required
                                    :error="passwordForm.errors.password"
                                    autocomplete="new-password"
                                    class="pl-10 pr-10"
                                />
                                <button
                                    type="button"
                                    @click="showNewPassword = !showNewPassword"
                                    class="absolute inset-y-0 right-0 pr-3 flex items-center text-gray-400 hover:text-gray-600 dark:hover:text-slate-300"
                                >
                                    <EyeIcon v-if="!showNewPassword" class="h-5 w-5" />
                                    <EyeSlashIcon v-else class="h-5 w-5" />
                                </button>
                            </div>
                            <InputError :message="passwordForm.errors.password" />
                        </div>

                        <!-- Confirm Password -->
                        <div>
                            <InputLabel for="password_confirmation" value="Confirmar Nueva Contraseña" required />
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                    <LockClosedIcon class="h-5 w-5 text-gray-400" />
                                </div>
                                <Input
                                    id="password_confirmation"
                                    v-model="passwordForm.password_confirmation"
                                    :type="showConfirmPassword ? 'text' : 'password'"
                                    required
                                    :error="passwordForm.errors.password_confirmation"
                                    autocomplete="new-password"
                                    class="pl-10 pr-10"
                                />
                                <button
                                    type="button"
                                    @click="showConfirmPassword = !showConfirmPassword"
                                    class="absolute inset-y-0 right-0 pr-3 flex items-center text-gray-400 hover:text-gray-600 dark:hover:text-slate-300"
                                >
                                    <EyeIcon v-if="!showConfirmPassword" class="h-5 w-5" />
                                    <EyeSlashIcon v-else class="h-5 w-5" />
                                </button>
                            </div>
                            <InputError :message="passwordForm.errors.password_confirmation" />
                        </div>

                        <!-- Actions -->
                        <div class="flex items-center justify-end">
                            <Button
                                type="submit"
                                variant="primary"
                                :loading="passwordForm.processing"
                                :disabled="passwordForm.processing"
                            >
                                Actualizar Contraseña
                            </Button>
                        </div>
                    </form>
                </div>
            </Card>

            <!-- Delete Account -->
            <Card>
                <div class="card-header">
                    <div class="flex items-center gap-2">
                        <ExclamationTriangleIcon class="h-6 w-6 text-red-600 dark:text-red-400" />
                        <h2 class="text-lg font-semibold text-gray-900 dark:text-white">
                            Eliminar Cuenta
                        </h2>
                    </div>
                    <p class="text-sm text-gray-600 dark:text-slate-400 mt-1">
                        Una vez eliminada tu cuenta, todos sus recursos y datos serán eliminados permanentemente
                    </p>
                </div>

                <div class="card-body">
                    <Alert type="error">
                        <div class="text-sm space-y-2">
                            <p class="font-medium">¡Advertencia! Esta acción es irreversible.</p>
                            <p>Al eliminar tu cuenta, se eliminarán:</p>
                            <ul class="list-disc list-inside ml-2 space-y-1">
                                <li>Todas tus importaciones y datos asociados</li>
                                <li>Todos tus gastos y productos registrados</li>
                                <li>Todas tus empresas y configuraciones</li>
                                <li>Tu perfil y preferencias</li>
                            </ul>
                        </div>
                    </Alert>

                    <div class="mt-6 flex justify-end">
                        <Button
                            variant="danger"
                            @click="confirmDeleteAccount"
                        >
                            Eliminar Cuenta
                        </Button>
                    </div>
                </div>
            </Card>
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
                        Continuar
                    </Button>
                </div>
            </template>
        </Modal>

        <!-- Delete Account Password Modal -->
        <Modal :show="showDeleteModal" @close="showDeleteModal = false">
            <template #header>
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                    Confirmar Eliminación de Cuenta
                </h3>
            </template>

            <div class="space-y-4">
                <p class="text-gray-600 dark:text-slate-400">
                    Por favor, ingresa tu contraseña para confirmar que deseas eliminar permanentemente tu cuenta.
                </p>

                <div>
                    <InputLabel for="delete_password" value="Contraseña" required />
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <LockClosedIcon class="h-5 w-5 text-gray-400" />
                        </div>
                        <Input
                            id="delete_password"
                            v-model="deleteAccountPassword"
                            :type="showDeletePassword ? 'text' : 'password'"
                            placeholder="Ingresa tu contraseña"
                            required
                            :error="deleteError"
                            @keydown.enter="deleteAccount"
                            class="pl-10 pr-10"
                        />
                        <button
                            type="button"
                            @click="showDeletePassword = !showDeletePassword"
                            class="absolute inset-y-0 right-0 pr-3 flex items-center text-gray-400 hover:text-gray-600 dark:hover:text-slate-300"
                        >
                            <EyeIcon v-if="!showDeletePassword" class="h-5 w-5" />
                            <EyeSlashIcon v-else class="h-5 w-5" />
                        </button>
                    </div>
                    <InputError :message="deleteError" />
                </div>
            </div>

            <template #footer>
                <div class="flex items-center justify-end gap-3">
                    <Button
                        variant="secondary"
                        @click="showDeleteModal = false"
                    >
                        Cancelar
                    </Button>
                    <Button
                        variant="danger"
                        @click="deleteAccount"
                        :disabled="!deleteAccountPassword"
                    >
                        Eliminar Mi Cuenta
                    </Button>
                </div>
            </template>
        </Modal>
    </AuthenticatedLayout>
</template>
