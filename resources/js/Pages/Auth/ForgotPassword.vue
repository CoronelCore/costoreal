<script setup>
import { inject } from 'vue';
import { useForm, Link } from '@inertiajs/vue3';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import Button from '@/Components/UI/Button.vue';
import Input from '@/Components/UI/Input.vue';
import InputLabel from '@/Components/UI/InputLabel.vue';
import InputError from '@/Components/UI/InputError.vue';

// Ziggy route helper
const route = inject('route');

defineProps({
    status: {
        type: String,
        default: null
    }
});

const form = useForm({
    email: ''
});

const submit = () => {
    form.post(route('password.email'));
};
</script>

<template>
    <GuestLayout>
        <div class="mb-6">
            <h2 class="text-2xl font-bold text-gray-900 dark:text-white">
                Recuperar Contraseña
            </h2>
            <p class="mt-2 text-sm text-gray-600 dark:text-slate-400">
                ¿Olvidaste tu contraseña? No hay problema. Solo ingresa tu correo electrónico
                y te enviaremos un enlace para que puedas elegir una nueva.
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
                <Input
                    id="email"
                    v-model="form.email"
                    type="email"
                    name="email"
                    required
                    autocomplete="username"
                    placeholder="tu@email.com"
                    :error="form.errors.email"
                />
                <InputError :message="form.errors.email" />
            </div>

            <!-- Actions -->
            <div class="flex items-center justify-end gap-4">
                <Link
                    :href="route('login')"
                    class="text-sm text-gray-600 hover:text-gray-900 dark:text-slate-400 dark:hover:text-slate-200 transition-colors"
                >
                    Volver al inicio
                </Link>

                <Button
                    type="submit"
                    variant="primary"
                    :loading="form.processing"
                    :disabled="form.processing"
                >
                    Enviar Enlace
                </Button>
            </div>
        </form>
    </GuestLayout>
</template>
