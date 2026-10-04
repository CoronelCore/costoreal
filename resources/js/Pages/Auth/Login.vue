<script setup>
import { ref, inject } from 'vue';
import { useForm, Link } from '@inertiajs/vue3';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import Button from '@/Components/UI/Button.vue';
import Input from '@/Components/UI/Input.vue';
import InputLabel from '@/Components/UI/InputLabel.vue';
import InputError from '@/Components/UI/InputError.vue';
import { EnvelopeIcon, LockClosedIcon, EyeIcon, EyeSlashIcon } from '@heroicons/vue/24/outline';

// Ziggy route helper
const route = inject('route');

defineProps({
    canResetPassword: {
        type: Boolean,
        default: false
    },
    status: {
        type: String,
        default: null
    }
});

const form = useForm({
    email: '',
    password: '',
    remember: false
});

const showPassword = ref(false);

const togglePasswordVisibility = () => {
    showPassword.value = !showPassword.value;
};

const submit = () => {
    form.post(route('login'), {
        onFinish: () => form.reset('password'),
    });
};
</script>

<template>
    <GuestLayout>
        <div class="mb-6">
            <h2 class="text-2xl font-bold text-gray-900 dark:text-white">
                Iniciar Sesión
            </h2>
            <p class="mt-1 text-sm text-gray-600 dark:text-slate-400">
                Ingresa a tu cuenta para continuar
            </p>
        </div>

        <!-- Status message -->
        <div v-if="status" class="mb-4 alert alert-success">
            {{ status }}
        </div>

        <form @submit.prevent="submit" class="space-y-5">
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
                        autocomplete="current-password"
                        placeholder="••••••••"
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

            <!-- Remember Me -->
            <div class="flex items-center">
                <input
                    id="remember"
                    v-model="form.remember"
                    type="checkbox"
                    name="remember"
                    class="h-4 w-4 rounded-sm border-gray-300 text-primary-600 focus:ring-primary-500 dark:border-slate-600 dark:bg-slate-800 cursor-pointer transition-all duration-200"
                >
                <label for="remember" class="ml-2 block text-sm text-gray-700 dark:text-slate-300 cursor-pointer select-none">
                    Recordarme
                </label>
            </div>

            <!-- Actions -->
            <div class="flex items-center justify-between gap-4">
                <Link
                    v-if="canResetPassword"
                    :href="route('password.request')"
                    class="text-sm text-primary-600 hover:text-primary-700 dark:text-primary-400 dark:hover:text-primary-300 transition-colors hover:underline"
                >
                    ¿Olvidaste tu contraseña?
                </Link>
                <div v-else />

                <Button
                    type="submit"
                    variant="primary"
                    :loading="form.processing"
                    :disabled="form.processing"
                    class="btn-gradient"
                >
                    Iniciar Sesión
                </Button>
            </div>
        </form>

        <!-- Register Link -->
        <div class="mt-6 text-center">
            <p class="text-sm text-gray-600 dark:text-slate-400">
                ¿No tienes cuenta?
                <Link
                    :href="route('register')"
                    class="font-medium text-primary-600 hover:text-primary-700 dark:text-primary-400 dark:hover:text-primary-300 transition-colors hover:underline decoration-2 underline-offset-2"
                >
                    Regístrate aquí
                </Link>
            </p>
        </div>
    </GuestLayout>
</template>
