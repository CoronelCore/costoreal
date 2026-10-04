<script setup lang="ts">
import { Head, useForm } from "@inertiajs/vue3";
import GuestLayout from "@/Layouts/GuestLayout.vue";
import { useRoute } from "@/Composables/useRoute";
defineProps<{ status?: string | null }>();
const route = useRoute();
const form = useForm({});
</script>
<template>
    <Head title="Verificar correo" /><GuestLayout
        ><p class="f-eyebrow">ACTIVA TU CUENTA</p>
        <h1>Verificar correo</h1>
        <p class="f-auth-subtitle">
            Abre el enlace de verificación de tu correo para continuar. Puedes
            solicitar otro si no lo recibiste.
        </p>
        <p
            v-if="status === 'verification-link-sent'"
            class="f-notice f-notice-success"
            role="status"
        >
            Se ha solicitado un nuevo enlace de verificación.
        </p>
        <form @submit.prevent="form.post(route('verification.send'))">
            <button
                class="f-button f-button-primary f-full"
                :disabled="form.processing"
            >
                {{ form.processing ? "Solicitando…" : "Solicitar otro enlace" }}
            </button>
        </form>
        <button
            class="f-text-link f-auth-bottom"
            :disabled="form.processing"
            @click="form.post(route('logout'))"
        >
            Cerrar sesión
        </button></GuestLayout
    >
</template>
