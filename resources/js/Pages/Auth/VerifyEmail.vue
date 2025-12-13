<script setup>
import { computed, inject } from 'vue';
import { useForm, usePage, Link } from '@inertiajs/vue3';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import Button from '@/Components/UI/Button.vue';

// Ziggy route helper
const route = inject('route');

const props = defineProps({
    status: {
        type: String,
        default: null
    }
});

const page = usePage();
const form = useForm({});

const verificationLinkSent = computed(() => props.status === 'verification-link-sent');

const submit = () => {
    form.post(route('verification.send'));
};

const logout = () => {
    form.post(route('logout'));
};
</script>

<template>
    <GuestLayout>
        <div class="mb-6">
            <h2 class="text-2xl font-bold text-gray-900 dark:text-white">
                Verificar Correo Electrónico
            </h2>
            <p class="mt-2 text-sm text-gray-600 dark:text-slate-400">
                Gracias por registrarte! Antes de comenzar, ¿podrías verificar tu dirección de
                correo electrónico haciendo clic en el enlace que te acabamos de enviar? Si no
                recibiste el correo, con gusto te enviaremos otro.
            </p>
        </div>

        <!-- Success message -->
        <div v-if="verificationLinkSent" class="mb-4 alert alert-success">
            Se ha enviado un nuevo enlace de verificación a tu correo electrónico.
        </div>

        <form @submit.prevent="submit" class="space-y-4">
            <Button
                type="submit"
                variant="primary"
                :loading="form.processing"
                :disabled="form.processing"
                class="w-full"
            >
                Reenviar Correo de Verificación
            </Button>
        </form>

        <div class="mt-6 text-center">
            <button
                @click="logout"
                type="button"
                class="text-sm text-gray-600 hover:text-gray-900 dark:text-slate-400 dark:hover:text-slate-200 transition-colors underline"
            >
                Cerrar Sesión
            </button>
        </div>
    </GuestLayout>
</template>
