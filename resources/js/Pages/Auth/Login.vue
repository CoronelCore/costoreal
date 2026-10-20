<script setup lang="ts">
import { Head, Link, useForm } from "@inertiajs/vue3";
import GuestLayout from "@/Layouts/GuestLayout.vue";
import FinancialField from "@/Components/UI/FinancialField.vue";
import { useRoute } from "@/Composables/useRoute";
import { focusInvalid } from "@/Composables/useFormFocus";
const route = useRoute();
defineProps<{ canResetPassword?: boolean; status?: string | null }>();
const form = useForm({ email: "", password: "", remember: false });
const submit = () =>
    form.post(route("login"), {
        onFinish: () => {
            form.reset("password");
            void focusInvalid();
        },
    });
</script>
<template>
    <Head title="Iniciar sesión" /><GuestLayout>
        <p class="f-eyebrow">BIENVENIDO A COSTOREAL</p>
        <h1>Iniciar sesión</h1>
        <p class="f-auth-subtitle">
            Accede a tus importaciones y su historial.
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
            />
            <FinancialField
                id="password"
                label="Contraseña"
                v-model="form.password"
                type="password"
                autocomplete="current-password"
                required
                :error="form.errors.password"
                :disabled="form.processing"
            />
            <label class="f-check"
                ><input
                    id="remember"
                    type="checkbox"
                    v-model="form.remember"
                    :disabled="form.processing"
                />
                Mantener sesión iniciada</label
            >
            <button
                class="f-button f-button-primary f-full"
                :disabled="form.processing"
            >
                {{ form.processing ? "Entrando…" : "Iniciar sesión" }}
            </button>
            <Link
                v-if="canResetPassword"
                :href="route('password.request')"
                class="f-text-link"
                >¿Olvidaste tu contraseña?</Link
            >
        </form>
        <p class="f-auth-bottom">
            ¿Primera vez aquí?
            <Link class="f-text-link" :href="route('register')"
                >Crear cuenta</Link
            >
        </p>
    </GuestLayout>
</template>
