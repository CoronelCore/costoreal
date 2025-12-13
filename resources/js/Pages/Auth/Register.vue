<script setup>
import { ref, inject } from 'vue';
import { useForm, Link } from '@inertiajs/vue3';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import Button from '@/Components/UI/Button.vue';
import Input from '@/Components/UI/Input.vue';
import InputLabel from '@/Components/UI/InputLabel.vue';
import InputError from '@/Components/UI/InputError.vue';
import { EnvelopeIcon, LockClosedIcon, UserIcon, EyeIcon, EyeSlashIcon } from '@heroicons/vue/24/outline';

// Ziggy route helper
const route = inject('route');

const form = useForm({
    name: '',
    email: '',
    password: '',
    password_confirmation: ''
});

const showPassword = ref(false);
const showPasswordConfirmation = ref(false);

const togglePasswordVisibility = () => {
    showPassword.value = !showPassword.value;
};

const togglePasswordConfirmationVisibility = () => {
    showPasswordConfirmation.value = !showPasswordConfirmation.value;
};

const submit = () => {
    form.post(route('register'), {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
};
</script>

<template>
    <GuestLayout>
        <div class="mb-6">
            <h2 class="text-2xl font-bold text-gray-900 dark:text-white">
                Crear Cuenta
            </h2>
            <p class="mt-1 text-sm text-gray-600 dark:text-slate-400">
                Regístrate para comenzar a usar CostoReal
            </p>
        </div>

        <form @submit.prevent="submit" class="space-y-5">
            <!-- Name -->
            <div>
                <InputLabel for="name" value="Nombre Completo" required />
                <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <UserIcon class="h-5 w-5 text-gray-400 dark:text-slate-500" />
                    </div>
                    <Input
                        id="name"
                        v-model="form.name"
                        type="text"
                        name="name"
                        required
                        autocomplete="name"
                        placeholder="Juan Pérez"
                        :error="form.errors.name"
                        class="pl-10"
                    />
                </div>
                <InputError :message="form.errors.name" />
            </div>

            <!-- Email -->
            <div>
                <InputLabel for="email" value="Correo Electrónico" required />
                <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <EnvelopeIcon class="h-5 w-5 text-gray-400 dark:text-slate-500" />
                    </div>
                    <Input
                        id="email"
                        v-model="form.email"
                        type="email"
                        name="email"
                        required
                        autocomplete="username"
                        placeholder="tu@email.com"
                        :error="form.errors.email"
                        class="pl-10"
                    />
                </div>
                <InputError :message="form.errors.email" />
            </div>

            <!-- Password -->
            <div>
                <InputLabel for="password" value="Contraseña" required />
                <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <LockClosedIcon class="h-5 w-5 text-gray-400 dark:text-slate-500" />
                    </div>
                    <Input
                        id="password"
                        v-model="form.password"
                        :type="showPassword ? 'text' : 'password'"
                        name="password"
                        required
                        autocomplete="new-password"
                        placeholder="Mínimo 8 caracteres"
                        :error="form.errors.password"
                        class="pl-10 pr-10"
                    />
                    <button
                        type="button"
                        @click="togglePasswordVisibility"
                        class="absolute inset-y-0 right-0 pr-3 flex items-center"
                    >
                        <EyeSlashIcon v-if="showPassword" class="h-5 w-5 text-gray-400 hover:text-gray-600 dark:text-slate-500 dark:hover:text-slate-400 transition-colors" />
                        <EyeIcon v-else class="h-5 w-5 text-gray-400 hover:text-gray-600 dark:text-slate-500 dark:hover:text-slate-400 transition-colors" />
                    </button>
                </div>
                <InputError :message="form.errors.password" />
            </div>

            <!-- Password Confirmation -->
            <div>
                <InputLabel for="password_confirmation" value="Confirmar Contraseña" required />
                <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <LockClosedIcon class="h-5 w-5 text-gray-400 dark:text-slate-500" />
                    </div>
                    <Input
                        id="password_confirmation"
                        v-model="form.password_confirmation"
                        :type="showPasswordConfirmation ? 'text' : 'password'"
                        name="password_confirmation"
                        required
                        autocomplete="new-password"
                        placeholder="Repite tu contraseña"
                        :error="form.errors.password_confirmation"
                        class="pl-10 pr-10"
                    />
                    <button
                        type="button"
                        @click="togglePasswordConfirmationVisibility"
                        class="absolute inset-y-0 right-0 pr-3 flex items-center"
                    >
                        <EyeSlashIcon v-if="showPasswordConfirmation" class="h-5 w-5 text-gray-400 hover:text-gray-600 dark:text-slate-500 dark:hover:text-slate-400 transition-colors" />
                        <EyeIcon v-else class="h-5 w-5 text-gray-400 hover:text-gray-600 dark:text-slate-500 dark:hover:text-slate-400 transition-colors" />
                    </button>
                </div>
                <InputError :message="form.errors.password_confirmation" />
            </div>

            <!-- Actions -->
            <div class="flex items-center justify-end">
                <Button
                    type="submit"
                    variant="primary"
                    :loading="form.processing"
                    :disabled="form.processing"
                    class="w-full sm:w-auto btn-gradient"
                >
                    Registrarse
                </Button>
            </div>
        </form>

        <!-- Login Link -->
        <div class="mt-6 text-center">
            <p class="text-sm text-gray-600 dark:text-slate-400">
                ¿Ya tienes cuenta?
                <Link
                    :href="route('login')"
                    class="font-medium text-primary-600 hover:text-primary-700 dark:text-primary-400 dark:hover:text-primary-300 transition-colors hover:underline decoration-2 underline-offset-2"
                >
                    Inicia sesión aquí
                </Link>
            </p>
        </div>
    </GuestLayout>
</template>
