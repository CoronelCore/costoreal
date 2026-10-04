<script setup lang="ts">
import { Head, Link, useForm } from "@inertiajs/vue3";
import GuestLayout from "@/Layouts/GuestLayout.vue";
import FinancialField from "@/Components/UI/FinancialField.vue";
import { useRoute } from "@/Composables/useRoute";
import { focusInvalid } from "@/Composables/useFormFocus";
const route = useRoute();
defineProps<{ status?: string | null }>();
const form = useForm({ email: "" });
const submit = () =>
    form.post(route("password.email"), { onFinish: () => void focusInvalid() });
</script>
<template>
    <Head title="Recuperar acceso" /><GuestLayout
        ><p class="f-eyebrow">ACCESO A TU CUENTA</p>
        <h1>Recuperar acceso</h1>
        <p class="f-auth-subtitle">
            Indica tu correo para solicitar un enlace de recuperación.
        </p>
        <p v-if="status" class="f-notice f-notice-success" role="status">
            {{ status }}
        </p>
        <form class="f-editor-form" @submit.prevent="submit" novalidate>
            <FinancialField
                id="email"
                label="Correo electrónico"
                v-model="form.email"
                type="email"
                autocomplete="username"
                required
                :error="form.errors.email"
                :disabled="form.processing"
            /><button
                class="f-button f-button-primary f-full"
                :disabled="form.processing"
            >
                {{ form.processing ? "Solicitando…" : "Solicitar enlace" }}
            </button>
        </form>
        <p class="f-auth-bottom">
            <Link :href="route('login')" class="f-text-link"
                >Volver a iniciar sesión</Link
            >
        </p></GuestLayout
    >
</template>
