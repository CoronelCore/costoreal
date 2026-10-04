<script setup lang="ts">
import { Head, Link, useForm } from "@inertiajs/vue3";
import GuestLayout from "@/Layouts/GuestLayout.vue";
import FinancialField from "@/Components/UI/FinancialField.vue";
import { useRoute } from "@/Composables/useRoute";
import { focusInvalid } from "@/Composables/useFormFocus";
const route = useRoute();
const form = useForm({ password: "" });
const submit = () =>
    form.post(route("password.confirm"), {
        onFinish: () => {
            form.reset();
            void focusInvalid();
        },
    });
</script>
<template>
    <Head title="Confirmar acceso" /><GuestLayout
        ><p class="f-eyebrow">SEGURIDAD DE TU CUENTA</p>
        <h1>Confirmar acceso</h1>
        <p class="f-auth-subtitle">Confirma tu contraseña para continuar.</p>
        <form class="f-editor-form" @submit.prevent="submit" novalidate>
            <FinancialField
                id="password"
                label="Contraseña"
                v-model="form.password"
                type="password"
                autocomplete="current-password"
                required
                :error="form.errors.password"
                :disabled="form.processing"
            /><button
                class="f-button f-button-primary f-full"
                :disabled="form.processing"
            >
                {{ form.processing ? "Comprobando…" : "Confirmar" }}
            </button>
        </form></GuestLayout
    >
</template>
