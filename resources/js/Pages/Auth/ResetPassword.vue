<script setup lang="ts">
import { Head, Link, useForm } from "@inertiajs/vue3";
import GuestLayout from "@/Layouts/GuestLayout.vue";
import FinancialField from "@/Components/UI/FinancialField.vue";
import { useRoute } from "@/Composables/useRoute";
import { focusInvalid } from "@/Composables/useFormFocus";
const route = useRoute();
const props = defineProps<{ email: string; token: string }>();
const form = useForm({
    email: props.email,
    token: props.token,
    password: "",
    password_confirmation: "",
});
const submit = () =>
    form.post(route("password.store"), {
        onFinish: () => {
            form.reset("password", "password_confirmation");
            void focusInvalid();
        },
    });
</script>
<template>
    <Head title="Restablecer contraseña" /><GuestLayout
        ><p class="f-eyebrow">RECUPERA TU ACCESO</p>
        <h1>Restablecer contraseña</h1>
        <p class="f-auth-subtitle">Elige la nueva contraseña para tu cuenta.</p>
        <form class="f-editor-form" @submit.prevent="submit" novalidate>
            <FinancialField
                id="email"
                label="Correo electrónico"
                v-model="form.email"
                type="email"
                readonly
                autocomplete="username"
                :error="form.errors.email"
            /><FinancialField
                id="password"
                label="Nueva contraseña"
                v-model="form.password"
                type="password"
                autocomplete="new-password"
                :error="form.errors.password"
                :disabled="form.processing"
            /><FinancialField
                id="password_confirmation"
                label="Confirmar contraseña"
                v-model="form.password_confirmation"
                type="password"
                autocomplete="new-password"
                :error="form.errors.password_confirmation"
                :disabled="form.processing"
            /><button
                class="f-button f-button-primary f-full"
                :disabled="form.processing"
            >
                {{ form.processing ? "Guardando…" : "Restablecer contraseña" }}
            </button>
        </form>
        <p class="f-auth-bottom">
            <Link class="f-text-link" :href="route('login')"
                >Volver a iniciar sesión</Link
            >
        </p></GuestLayout
    >
</template>
