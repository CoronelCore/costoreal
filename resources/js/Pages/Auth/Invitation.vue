<script setup lang="ts">
import { Head, Link, useForm } from "@inertiajs/vue3";
import GuestLayout from "@/Layouts/GuestLayout.vue";
import FinancialField from "@/Components/UI/FinancialField.vue";
import AccountNotice from "@/Components/People/AccountNotice.vue";
import { useRoute } from "@/Composables/useRoute";
import { focusInvalid } from "@/Composables/useFormFocus";
const props = defineProps<{
    token: string;
    invitation: {
        name: string;
        email: string;
        owner: string | null;
        beta: boolean;
    } | null;
}>();
const route = useRoute();
const owner = props.invitation?.owner || "Quien administra CostoReal";
const form = useForm<{
    name: string;
    email: string;
    password: string;
    password_confirmation: string;
    understood: boolean;
    invitation?: string;
}>({
    name: props.invitation?.name ?? "",
    email: props.invitation?.email ?? "",
    password: "",
    password_confirmation: "",
    understood: false,
});
const submit = () =>
    form.post(route("invitations.store", props.token), {
        onFinish: () => {
            form.reset("password", "password_confirmation");
            void focusInvalid();
        },
    });
</script>
<template>
    <Head title="Crear tu cuenta" /><GuestLayout>
        <template v-if="invitation">
            <p class="f-eyebrow">TE INVITARON</p>
            <h1>Crea tu cuenta</h1>
            <p class="f-auth-subtitle">
                {{ owner }} te invitó a probar CostoReal: calcula cuánto te costó
                de verdad cada producto que traes de afuera.
            </p>
            <p
                v-if="form.errors.invitation"
                class="f-notice f-notice-error"
                role="alert"
            >
                {{ form.errors.invitation }}
            </p>
            <form class="f-editor-form" @submit.prevent="submit" novalidate>
                <FinancialField
                    id="name"
                    label="Tu nombre"
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
                    help="Al menos 10 caracteres, con letras y números."
                    required
                    :error="form.errors.password"
                    :disabled="form.processing"
                />
                <FinancialField
                    id="password_confirmation"
                    label="Repite la contraseña"
                    v-model="form.password_confirmation"
                    type="password"
                    autocomplete="new-password"
                    required
                    :error="form.errors.password_confirmation"
                    :disabled="form.processing"
                />
                <AccountNotice
                    v-model="form.understood"
                    :owner="owner"
                    :beta="invitation.beta"
                    :error="form.errors.understood"
                    :disabled="form.processing"
                />
                <button
                    class="f-button f-button-primary f-full"
                    :disabled="form.processing"
                >
                    {{ form.processing ? "Creando cuenta…" : "Crear mi cuenta" }}
                </button>
            </form>
        </template>
        <template v-else>
            <p class="f-eyebrow">INVITACIÓN</p>
            <h1>Este enlace ya no sirve</h1>
            <p class="f-notice" role="status">
                Se usó, venció o fue anulado. Pídele un enlace nuevo a quien te
                invitó. Si ya creaste tu cuenta, inicia sesión.
            </p>
        </template>
        <p class="f-auth-bottom">
            ¿Ya tienes una cuenta?
            <Link :href="route('login')" class="f-text-link">Iniciar sesión</Link>
        </p></GuestLayout
    >
</template>
