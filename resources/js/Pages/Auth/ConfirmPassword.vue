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

const form = useForm({
    password: ''
});

const submit = () => {
    form.post(route('password.confirm'), {
        onFinish: () => form.reset(),
    });
};
</script>

<template>
    <GuestLayout>
        <div class="mb-6">
            <h2 class="text-2xl font-bold text-gray-900 dark:text-white">
                Confirmar Contraseña
            </h2>
            <p class="mt-2 text-sm text-gray-600 dark:text-slate-400">
                Esta es un área segura de la aplicación. Por favor, confirma tu contraseña
                antes de continuar.
            </p>
        </div>

        <form @submit.prevent="submit" class="space-y-5">
            <!-- Password -->
            <div>
                <InputLabel for="password" value="Contraseña" required />
                <Input
                    id="password"
                    v-model="form.password"
                    type="password"
                    name="password"
                    required
                    autocomplete="current-password"
                    placeholder="••••••••"
                    :error="form.errors.password"
                />
                <InputError :message="form.errors.password" />
            </div>

            <!-- Actions -->
            <div class="flex items-center justify-end">
                <Button
                    type="submit"
                    variant="primary"
                    :loading="form.processing"
                    :disabled="form.processing"
                >
                    Confirmar
                </Button>
            </div>
        </form>
    </GuestLayout>
</template>
