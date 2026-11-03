<script setup lang="ts">
import { computed, ref } from "vue";
import { Head, router, useForm, usePage } from "@inertiajs/vue3";
import FinancialLayout from "@/Layouts/FinancialLayout.vue";
import Modal from "@/Components/UI/Modal.vue";
import OneTimeLink from "@/Components/People/OneTimeLink.vue";
import { useRoute } from "@/Composables/useRoute";
import { count } from "@/Utils/format";

interface Person {
    id: number;
    name: string;
    email: string;
    created_at: string | null;
    last_seen_at: string | null;
    suspended: boolean;
    is_owner: boolean;
    trips_count: number;
}
interface PendingInvitation {
    id: number;
    name: string;
    email: string;
    expires_at: string;
    status: "pending" | "expired";
}
const props = defineProps<{
    people: Person[];
    invitations: PendingInvitation[];
    registration_open: boolean;
}>();
const route = useRoute();
const page = usePage();
const link = computed(() => page.flash?.link);
const invite = useForm({ name: "", email: "" });
const sendInvite = () =>
    invite.post(route("people.invite"), {
        preserveScroll: true,
        onSuccess: () => {
            invite.reset();
            window.scrollTo({ top: 0, behavior: "smooth" });
        },
    });
const revoking = ref<number | null>(null);
const revoke = (invitation: PendingInvitation) => {
    revoking.value = invitation.id;
    router.patch(
        route("people.revoke", invitation.id),
        {},
        { preserveScroll: true, onFinish: () => (revoking.value = null) },
    );
};
const registration = useForm({ registration_open: props.registration_open });
const saveRegistration = () =>
    registration.patch(route("people.registration"), {
        preserveScroll: true,
        onSuccess: () => registration.defaults(),
    });
const toSuspend = ref<Person | null>(null);
const busy = ref<number | null>(null);
const act = (person: Person, name: string, method: "patch" | "post") => {
    busy.value = person.id;
    router[method](
        route(name, person.id),
        {},
        {
            preserveScroll: true,
            onFinish: () => {
                busy.value = null;
                toSuspend.value = null;
            },
        },
    );
};
const day = (value: string | null) =>
    value
        ? new Date(value).toLocaleDateString("es-CL", { dateStyle: "medium" })
        : "—";
const relative = new Intl.RelativeTimeFormat("es-CL", { numeric: "auto" });
function seen(value: string | null) {
    if (!value) return "Nunca";
    const minutes = Math.round((new Date(value).getTime() - Date.now()) / 60000);
    if (minutes > -10) return "Hace un momento";
    if (minutes > -60) return relative.format(minutes, "minute");
    if (minutes > -1440) return relative.format(Math.round(minutes / 60), "hour");
    if (minutes > -10080) return relative.format(Math.round(minutes / 1440), "day");
    return day(value);
}
const state = (person: Person) =>
    person.is_owner ? "Tú · cuenta dueña" : person.suspended ? "Suspendida" : "Activa";
</script>
<template>
    <Head title="Personas" />
    <FinancialLayout>
        <div class="f-heading">
            <div>
                <div class="f-eyebrow">TU INSTALACIÓN</div>
                <h1>Personas</h1>
                <p>
                    Quién usa CostoReal en tu hosting. Ves su acceso, nunca sus
                    importaciones.
                </p>
            </div>
        </div>
        <div class="people-content">
            <OneTimeLink v-if="link" :link="link" />
            <section class="f-panel" aria-labelledby="invite-heading">
                <div class="f-panel-heading">
                    <h2 id="invite-heading">Invitar a alguien</h2>
                    <span class="f-hint">El registro sigue cerrado para el resto</span>
                </div>
                <form
                    class="f-panel-body"
                    @submit.prevent="sendInvite"
                    :aria-busy="invite.processing"
                    novalidate
                >
                    <p>
                        Escribe su nombre y su correo. Te damos un enlace de un
                        solo uso, que vence en 7 días, para que se lo mandes.
                    </p>
                    <div class="f-form-grid" style="margin-top: 14px">
                        <div class="f-field">
                            <label for="invite-name">Nombre</label
                            ><input
                                id="invite-name"
                                class="f-input"
                                v-model="invite.name"
                                autocomplete="off"
                                maxlength="255"
                                required
                                :disabled="invite.processing"
                                :aria-invalid="!!invite.errors.name"
                                aria-describedby="invite-name-error"
                            />
                            <p
                                id="invite-name-error"
                                v-if="invite.errors.name"
                                class="f-field-error"
                            >
                                {{ invite.errors.name }}
                            </p>
                        </div>
                        <div class="f-field">
                            <label for="invite-email">Correo electrónico</label
                            ><input
                                id="invite-email"
                                class="f-input"
                                v-model="invite.email"
                                type="email"
                                autocomplete="off"
                                maxlength="255"
                                required
                                :disabled="invite.processing"
                                :aria-invalid="!!invite.errors.email"
                                aria-describedby="invite-email-error"
                            />
                            <p
                                id="invite-email-error"
                                v-if="invite.errors.email"
                                class="f-field-error"
                            >
                                {{ invite.errors.email }}
                            </p>
                        </div>
                    </div>
                    <div class="f-actions" style="margin-top: 16px">
                        <button
                            class="f-button f-button-primary"
                            type="submit"
                            :disabled="invite.processing || !invite.name || !invite.email"
                        >
                            {{ invite.processing ? "Creando enlace…" : "Crear invitación" }}
                        </button>
                    </div>
                </form>
                <div v-if="invitations.length" class="f-table-scroll">
                    <table class="f-table">
                        <caption class="sr-only">
                            Invitaciones que aún no se usan
                        </caption>
                        <thead>
                            <tr>
                                <th scope="col">Invitación sin usar</th>
                                <th scope="col">Vence</th>
                                <th scope="col">Estado</th>
                                <th scope="col">
                                    <span class="sr-only">Acciones</span>
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="invitation in invitations" :key="invitation.id">
                                <td>
                                    <span class="f-table-title">{{ invitation.name }}</span
                                    ><span class="f-table-subtitle">{{ invitation.email }}</span>
                                </td>
                                <td>{{ day(invitation.expires_at) }}</td>
                                <td>
                                    <span
                                        class="f-status"
                                        :class="{ 'f-status-closed': invitation.status === 'expired' }"
                                        >{{ invitation.status === "expired" ? "Vencida" : "Esperando" }}</span
                                    >
                                </td>
                                <td>
                                    <div class="f-actions people-actions">
                                        <button
                                            class="f-button"
                                            type="button"
                                            :disabled="revoking === invitation.id"
                                            @click="revoke(invitation)"
                                        >
                                            {{ invitation.status === "expired" ? "Quitar" : "Anular" }}
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>
            <section class="f-panel" aria-labelledby="people-heading">
                <div class="f-panel-heading">
                    <h2 id="people-heading">
                        Cuentas <span class="f-muted">· {{ people.length }}</span>
                    </h2>
                    <span class="f-hint">Cada cuenta ve solo sus datos</span>
                </div>
                <div class="f-table-scroll">
                    <table class="f-table">
                        <caption class="sr-only">
                            Cuentas de esta instalación, su actividad y su
                            acceso
                        </caption>
                        <thead>
                            <tr>
                                <th scope="col">Persona</th>
                                <th scope="col">Desde</th>
                                <th scope="col">Última actividad</th>
                                <th scope="col">Importaciones</th>
                                <th scope="col">Estado</th>
                                <th scope="col">
                                    <span class="sr-only">Acciones</span>
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="person in people" :key="person.id">
                                <td>
                                    <span class="f-table-title">{{ person.name }}</span
                                    ><span class="f-table-subtitle">{{ person.email }}</span>
                                </td>
                                <td>{{ day(person.created_at) }}</td>
                                <td>{{ seen(person.last_seen_at) }}</td>
                                <td>{{ count(person.trips_count, "importación", "importaciones") }}</td>
                                <td>
                                    <span
                                        class="f-status"
                                        :class="{ 'f-status-closed': person.suspended }"
                                        >{{ state(person) }}</span
                                    >
                                </td>
                                <td>
                                    <div v-if="!person.is_owner" class="f-actions people-actions">
                                        <button
                                            class="f-button"
                                            type="button"
                                            :disabled="busy === person.id"
                                            @click="act(person, 'people.password-link', 'post')"
                                        >
                                            Enlace de contraseña</button
                                        ><button
                                            v-if="person.suspended"
                                            class="f-button"
                                            type="button"
                                            :disabled="busy === person.id"
                                            @click="act(person, 'people.restore', 'patch')"
                                        >
                                            Reactivar</button
                                        ><button
                                            v-else
                                            class="f-button f-button-danger"
                                            type="button"
                                            :disabled="busy === person.id"
                                            @click="toSuspend = person"
                                        >
                                            Suspender
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>
            <section class="f-panel" aria-labelledby="registration-heading">
                <div class="f-panel-heading">
                    <h2 id="registration-heading">Registro abierto</h2>
                    <span
                        class="f-status"
                        :class="{ 'f-status-closed': !registration_open }"
                        >{{ registration_open ? "Abierto" : "Cerrado" }}</span
                    >
                </div>
                <form
                    class="f-panel-body"
                    @submit.prevent="saveRegistration"
                    :aria-busy="registration.processing"
                >
                    <label class="f-check"
                        ><input
                            id="registration-open"
                            type="checkbox"
                            v-model="registration.registration_open"
                            :disabled="registration.processing"
                        />
                        Permitir que cualquiera con la dirección cree una
                        cuenta</label
                    >
                    <p class="f-hint" style="margin-top: 8px">
                        Mientras está abierto, cualquier persona que llegue a tu
                        sitio puede registrarse y usar tu hosting. Déjalo
                        cerrado si CostoReal es solo para ti o para tu negocio.
                    </p>
                    <p class="f-field-error" role="alert">
                        {{ registration.errors.registration_open }}
                    </p>
                    <div class="f-actions" style="margin-top: 14px">
                        <button
                            class="f-button f-button-primary"
                            type="submit"
                            :disabled="registration.processing || !registration.isDirty"
                        >
                            Guardar
                        </button>
                    </div>
                </form>
            </section>
        </div>
        <Modal
            :show="toSuspend !== null"
            title="Suspender acceso"
            :closeable="busy === null"
            @close="toSuspend = null"
        >
            <p>
                {{ toSuspend?.name }} no podrá volver a entrar y su sesión se
                cierra en su próxima visita. Sus importaciones se conservan y
                puedes reactivar su acceso cuando quieras.
            </p>
            <template #footer
                ><div class="f-actions">
                    <button
                        class="f-button"
                        :disabled="busy !== null"
                        @click="toSuspend = null"
                    >
                        Cancelar</button
                    ><button
                        class="f-button f-button-danger"
                        :disabled="busy !== null"
                        @click="toSuspend && act(toSuspend, 'people.suspend', 'patch')"
                    >
                        Suspender acceso
                    </button>
                </div></template
            >
        </Modal>
    </FinancialLayout>
</template>
<style scoped>
.people-content {
    max-width: 1100px;
    display: grid;
    gap: 18px;
}
.people-actions {
    justify-content: flex-end;
    flex-wrap: nowrap;
}
</style>
