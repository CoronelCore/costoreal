<script setup lang="ts">
import { Head, Link, useForm } from "@inertiajs/vue3";
import GuestLayout from "@/Layouts/GuestLayout.vue";
import FinancialField from "@/Components/UI/FinancialField.vue";
import { useRoute } from "@/Composables/useRoute";
import { focusInvalid } from "@/Composables/useFormFocus";
const route = useRoute();
const form = useForm({
    name: "",
    email: "",
    password: "",
    password_confirmation: "",
});
const submit = () =>
    form.post(route("register"), {
        onFinish: () => {
            form.reset("password", "password_confirmation");
            void focusInvalid();
        },
    });
</script>
<template>
    <Head title="Crear cuenta" /><GuestLayout
        ><p class="f-eyebrow">TU ESPACIO FINANCIERO</p>
        <h1>Crear cuenta</h1>
        <p class="f-auth-subtitle">Comienza a organizar tus importaciones.</p>
        <form class="f-editor-form" @submit.prevent="submit" novalidate>
            <FinancialField
                id="name"
                label="Nombre"
                v-model="form.name"
                autocomplete="name"
                required
                :error="form.errors.name"
                :disabled="form.processing"
            />
            <FinancialField
                id="email"
                label="Correo electrónico"
                v-model="form.email"
                type="email"
                autocomplete="username"
                required
                :error="form.errors.email"
                :disabled="form.processing"
            />
            <FinancialField
                id="password"
                label="Contraseña"
                v-model="form.password"
                type="password"
                autocomplete="new-password"
                required
                :error="form.errors.password"
                :disabled="form.processing"
            />
            <FinancialField
                id="password_confirmation"
                label="Confirmar contraseña"
                v-model="form.password_confirmation"
                type="password"
                autocomplete="new-password"
                required
                :error="form.errors.password_confirmation"
                :disabled="form.processing"
            />
            <button
                class="f-button f-button-primary f-full"
                :disabled="form.processing"
            >
                {{ form.processing ? "Creando cuenta…" : "Crear cuenta" }}
            </button>
        </form>
        <p class="f-auth-bottom">
            ¿Ya tienes una cuenta?
            <Link :href="route('login')" class="f-text-link"
                >Iniciar sesión</Link
            >
        </p></GuestLayout
    >
</template>
