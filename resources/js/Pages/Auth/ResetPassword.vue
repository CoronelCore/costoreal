<script setup>
import { inject } from 'vue';
import { useForm } from '@inertiajs/vue3';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import Button from '@/Components/UI/Button.vue';
import Input from '@/Components/UI/Input.vue';
import InputLabel from '@/Components/UI/InputLabel.vue';
import InputError from '@/Components/UI/InputError.vue';

// Ziggy route helper
const route = inject('route');

const props = defineProps({
    email: {
        type: String,
        required: true
    },
    token: {
        type: String,
        required: true
    }
});

const form = useForm({
    token: props.token,
    email: props.email,
    password: '',
    password_confirmation: ''
});

const submit = () => {
    form.post(route('password.store'), {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
};
</script>

<template>
    <GuestLayout>
        <div class="mb-6">
            <h2 class="text-2xl font-bold text-gray-900 dark:text-white">
                Restablecer Contraseña
            </h2>
            <p class="mt-1 text-sm text-gray-600 dark:text-slate-400">
                Ingresa tu nueva contraseña
            </p>
        </div>

        <form @submit.prevent="submit" class="space-y-5">
            <!-- Email (readonly) -->
            <div>
                <InputLabel for="email" value="Correo Electrónico" />
                <Input
                    id="email"
                    v-model="form.email"
                    type="email"
                    name="email"
                    required
                    autocomplete="username"
                    disabled
                />
            </div>

            <!-- Password -->
            <div>
                <InputLabel for="password" value="Nueva Contraseña" required />
                <Input
                    id="password"
                    v-model="form.password"
                    type="password"
                    name="password"
                    required
                    autocomplete="new-password"
                    placeholder="Mínimo 8 caracteres"
                    :error="form.errors.password"
                />
                <InputError :message="form.errors.password" />
            </div>

            <!-- Password Confirmation -->
            <div>
                <InputLabel for="password_confirmation" value="Confirmar Contraseña" required />
                <Input
                    id="password_confirmation"
                    v-model="form.password_confirmation"
                    type="password"
                    name="password_confirmation"
                    required
                    autocomplete="new-password"
                    placeholder="Repite tu contraseña"
                    :error="form.errors.password_confirmation"
                />
                <InputError :message="form.errors.password_confirmation" />
            </div>

            <!-- Actions -->
            <div class="flex items-center justify-end">
                <Button
                    type="submit"
                    variant="primary"
                    :loading="form.processing"
                    :disabled="form.processing"
                    class="w-full sm:w-auto"
                >
                    Restablecer Contraseña
                </Button>
            </div>
        </form>
    </GuestLayout>
</template>
