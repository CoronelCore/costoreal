<script setup lang="ts">
import { Head, Link, useForm } from "@inertiajs/vue3";
import GuestLayout from "@/Layouts/GuestLayout.vue";
import FinancialField from "@/Components/UI/FinancialField.vue";
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
                <section class="invitation-notice" aria-labelledby="notice-heading">
                    <h2 id="notice-heading">Antes de crear tu cuenta</h2>
                    <ul>
                        <li v-if="invitation.beta">
                            Es una versión de prueba: puede tener errores y
                            cambiar mientras {{ owner }} la mejora con lo que le
                            cuentes.
                        </li>
                        <li>
                            Lo que registres lo ves solo tú dentro de la app.
                        </li>
                        <li>
                            Los datos quedan en el hosting de {{ owner }}. Como lo
                            administra, puede llegar a la base de datos, y sus
                            respaldos incluyen los datos de todas las cuentas.
                        </li>
                        <li>
                            Si son datos delicados, prueba con montos de ejemplo.
                        </li>
                        <li>
                            Si ya no quieres usarla, avísale a {{ owner }}: puede
                            quitarte el acceso.
                        </li>
                    </ul>
                    <label class="f-check"
                        ><input
                            id="understood"
                            type="checkbox"
                            v-model="form.understood"
                            :disabled="form.processing"
                            :aria-invalid="!!form.errors.understood"
                            aria-describedby="understood-error"
                        />
                        Entendí cómo se guardan mis datos</label
                    >
                    <p
                        id="understood-error"
                        v-if="form.errors.understood"
                        class="f-field-error"
                    >
                        {{ form.errors.understood }}
                    </p>
                </section>
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
<style scoped>
.invitation-notice {
    border: 1px solid var(--f-line);
    border-radius: 10px;
    background: var(--f-subtle);
    padding: 14px 16px;
}
.invitation-notice h2 {
    font-size: 14px;
    font-weight: 600;
    margin: 0 0 8px;
}
.invitation-notice ul {
    margin: 0 0 12px;
    padding-left: 18px;
    list-style: disc;
    display: grid;
    gap: 6px;
    font-size: 13px;
    color: var(--f-muted);
}
</style>
