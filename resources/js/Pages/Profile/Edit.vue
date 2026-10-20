<script setup lang="ts">
import { ref, nextTick } from 'vue';
import { Head, useForm, usePage, router } from '@inertiajs/vue3';
import FinancialLayout from '@/Layouts/FinancialLayout.vue';
import Modal from '@/Components/UI/Modal.vue';
import { useRoute } from '@/Composables/useRoute';
const props = defineProps<{ mustVerifyEmail: boolean; emailVerified: boolean; status: string | null; accountDeletion: { can_delete: boolean; reason: string | null }; installation: { registration_open: boolean; backup_includes_database: boolean; last_backup_at: string | null } | null }>();
const route = useRoute();
const page = usePage();
const registration = useForm({ registration_open: props.installation?.registration_open ?? false });
function saveRegistration() { registration.patch(route('profile.registration'), { preserveScroll:true, onSuccess:() => registration.defaults() }); }
const profile = useForm({ name:page.props.auth.user?.name || '', email:page.props.auth.user?.email || '' });
const password = useForm({ current_password:'', password:'', password_confirmation:'' });
const deletion = useForm<{ password:string; account?:string }>({ password:'' });
const showDelete = ref(false);
const showPassword = ref<Record<string,boolean>>({});
const passwordFields = [
    { name:'current_password' as const, label:'Contraseña actual', autocomplete:'current-password' },
    { name:'password' as const, label:'Nueva contraseña', autocomplete:'new-password' },
    { name:'password_confirmation' as const, label:'Confirmar nueva contraseña', autocomplete:'new-password' },
];
async function focusError(errors: Record<string,string>, prefix: string) { await nextTick(); document.getElementById(`${prefix}-${Object.keys(errors)[0]}`)?.focus(); }
function saveProfile() {
    profile.patch(route('profile.update'), { errorBag:'profile', preserveScroll:true,
        onSuccess:() => { profile.name=page.props.auth.user?.name || ''; profile.email=page.props.auth.user?.email || ''; profile.defaults(); },
        onFinish:() => { focusError(profile.errors,'profile'); },
    });
}
function savePassword() {
    password.put(route('password.update'), { errorBag:'updatePassword', preserveScroll:true,
        onSuccess:() => password.reset(), onError:() => password.reset(),
        onFinish:() => { focusError(password.errors,'security'); },
    });
}
function closeDelete() { if (!deletion.processing) { showDelete.value=false; deletion.reset(); deletion.clearErrors(); } }
function deleteAccount() {
    deletion.delete(route('profile.destroy'), { errorBag:'userDeletion', preserveScroll:true,
        onSuccess:closeDelete,
        onError:() => deletion.reset('password'),
        onFinish:async () => { await nextTick(); document.getElementById(deletion.errors.account ? 'delete-account-error' : 'delete-account-password')?.focus(); },
    });
}
</script>
<template>
    <Head title="Perfil y seguridad" />
    <FinancialLayout>
        <div class="f-heading"><div><div class="f-eyebrow">TU ESPACIO PERSONAL</div><h1>Perfil y seguridad</h1><p>Información de tu cuenta, acceso y conservación de tus expedientes.</p></div></div>
        <div class="profile-content">
            <div v-if="status === 'profile-updated' || profile.recentlySuccessful" class="f-notice f-notice-success" role="status">Información de perfil actualizada.</div>
            <section class="f-panel" aria-labelledby="profile-heading"><div class="f-panel-heading"><h2 id="profile-heading">Información personal</h2></div><form class="f-panel-body" @submit.prevent="saveProfile" :aria-busy="profile.processing"><div class="f-form-grid"><div class="f-field"><label for="profile-name">Nombre</label><input id="profile-name" class="f-input" v-model="profile.name" autocomplete="name" maxlength="255" required :disabled="profile.processing" :aria-invalid="!!profile.errors.name" aria-describedby="profile-name-error" /><p id="profile-name-error" v-if="profile.errors.name" class="f-field-error">{{ profile.errors.name }}</p></div><div class="f-field"><label for="profile-email">Correo electrónico</label><input id="profile-email" class="f-input" v-model="profile.email" type="email" autocomplete="email" maxlength="255" required :disabled="profile.processing" :aria-invalid="!!profile.errors.email" aria-describedby="profile-email-error" /><p id="profile-email-error" v-if="profile.errors.email" class="f-field-error">{{ profile.errors.email }}</p></div></div><p v-if="mustVerifyEmail && !emailVerified" class="f-hint" style="margin-top:16px">Tu correo está pendiente de verificación. <button type="button" class="profile-link" @click="router.post(route('verification.send'))">Reenviar correo de verificación</button></p><div class="f-actions" style="margin-top:18px"><button class="f-button f-button-primary" :disabled="profile.processing">{{ profile.processing ? 'Guardando…' : 'Guardar perfil' }}</button></div></form></section>
            <div v-if="status === 'password-updated' || password.recentlySuccessful" class="f-notice f-notice-success" role="status">Contraseña actualizada.</div>
            <section class="f-panel" aria-labelledby="security-heading"><div class="f-panel-heading"><h2 id="security-heading">Cambiar contraseña</h2><span class="f-hint">Confirma tu acceso actual</span></div><form class="f-panel-body" @submit.prevent="savePassword" :aria-busy="password.processing"><div class="f-form-grid"><div v-for="field in passwordFields" :key="field.name" class="f-field"><label :for="`security-${field.name}`">{{ field.label }}</label><div class="profile-password"><input :id="`security-${field.name}`" class="f-input" v-model="password[field.name]" :type="showPassword[field.name] ? 'text' : 'password'" :autocomplete="field.autocomplete" required :disabled="password.processing" :aria-invalid="!!password.errors[field.name]" :aria-describedby="`security-${field.name}-error`" /><button type="button" class="f-button" :aria-label="`${showPassword[field.name] ? 'Ocultar' : 'Mostrar'} ${field.label.toLowerCase()}`" :aria-pressed="!!showPassword[field.name]" :disabled="password.processing" @click="showPassword[field.name] = !showPassword[field.name]">{{ showPassword[field.name] ? 'Ocultar' : 'Mostrar' }}</button></div><p :id="`security-${field.name}-error`" v-if="password.errors[field.name]" class="f-field-error">{{ password.errors[field.name] }}</p></div></div><div class="f-actions" style="margin-top:18px"><button class="f-button f-button-primary" :disabled="password.processing">{{ password.processing ? 'Guardando…' : 'Actualizar contraseña' }}</button></div></form></section>
            <div v-if="status === 'registration-opened' || status === 'registration-closed'" class="f-notice f-notice-success" role="status">{{ status === 'registration-opened' ? 'Ahora otras personas pueden crear una cuenta.' : 'El registro de cuentas nuevas quedó cerrado.' }}</div>
            <section v-if="installation" class="f-panel" aria-labelledby="access-heading"><div class="f-panel-heading"><h2 id="access-heading">Acceso de otras personas</h2><span class="f-status" :class="{ 'f-status-closed': !installation.registration_open }">{{ installation.registration_open ? 'Registro abierto' : 'Solo tú' }}</span></div><form class="f-panel-body" @submit.prevent="saveRegistration" :aria-busy="registration.processing"><label class="f-check"><input id="registration-open" type="checkbox" v-model="registration.registration_open" :disabled="registration.processing" /> Permitir que otras personas creen una cuenta en esta instalación</label><p class="f-hint" style="margin-top:8px">Cada cuenta ve solo sus datos, pero todas usan tu hosting. Déjalo cerrado si CostoReal es solo para ti o para tu negocio.</p><p class="f-field-error" role="alert">{{ registration.errors.registration_open }}</p><div class="f-actions" style="margin-top:14px"><button class="f-button f-button-primary" type="submit" :disabled="registration.processing || !registration.isDirty">Guardar</button></div></form></section>
            <section v-if="installation" class="f-panel" aria-labelledby="backup-heading"><div class="f-panel-heading"><h2 id="backup-heading">Respaldo</h2><span class="f-hint">{{ installation.last_backup_at ? 'Último: ' + new Date(installation.last_backup_at).toLocaleString('es-CL', { dateStyle: 'medium', timeStyle: 'short' }) : 'Aún no descargas uno' }}</span></div><div class="f-panel-body"><p>Descarga un archivo con {{ installation.backup_includes_database ? 'todos los datos y documentos de esta instalación' : 'los documentos de esta instalación' }} y guárdalo fuera del hosting: en tu computador o en tu nube. Adentro trae las instrucciones para restaurarlo.</p><p v-if="!installation.backup_includes_database" class="f-hint" style="margin-top:8px">Tus datos están en MySQL: respáldalos también desde hPanel (Archivos → Respaldos).</p><div class="f-actions" style="margin-top:16px"><a class="f-button f-button-primary" :href="route('profile.backup')">Descargar respaldo</a></div></div></section>
            <section class="f-panel" aria-labelledby="retention-heading"><div class="f-panel-heading"><h2 id="retention-heading">Conservación y cuenta</h2><span class="f-status">Sin borrado automático</span></div><div class="f-panel-body"><p>Las importaciones y sus documentos permanecen disponibles para recuperar desde la papelera. Los cierres y el historial conservan sus reportes originales.</p><p class="f-hint" style="margin-top:10px">Eliminar una cuenta vacía retira su perfil, empresas sin importaciones y categorías personales sin uso. Una cuenta con expedientes o registros asociados se conserva.</p><p v-if="accountDeletion.reason" class="f-notice" style="margin-top:18px">{{ accountDeletion.reason }}</p><div class="f-actions" style="margin-top:18px"><button class="f-button f-button-danger" :disabled="!accountDeletion.can_delete" @click="showDelete=true">Eliminar cuenta vacía</button></div></div></section>
        </div>
        <Modal :show="showDelete" title="Eliminar cuenta vacía" :closeable="!deletion.processing" @close="closeDelete"><p>Esta acción elimina permanentemente tu perfil, las empresas sin importaciones y las categorías sin uso. Confirma con tu contraseña actual.</p><p v-if="deletion.errors.account" id="delete-account-error" tabindex="-1" class="f-notice f-notice-error" role="alert" style="margin-top:14px">{{ deletion.errors.account }}</p><form id="delete-account-form" @submit.prevent="deleteAccount" class="f-field" style="margin-top:18px"><label for="delete-account-password">Contraseña actual</label><input id="delete-account-password" class="f-input" v-model="deletion.password" type="password" autocomplete="current-password" required :disabled="deletion.processing" :aria-invalid="!!deletion.errors.password" aria-describedby="delete-password-error" /><p id="delete-password-error" v-if="deletion.errors.password" class="f-field-error">{{ deletion.errors.password }}</p></form><template #footer><div class="f-actions"><button class="f-button" :disabled="deletion.processing" @click="closeDelete">Cancelar</button><button type="submit" form="delete-account-form" class="f-button f-button-danger" :disabled="deletion.processing || !deletion.password">{{ deletion.processing ? 'Eliminando…' : 'Eliminar mi cuenta' }}</button></div></template></Modal>
    </FinancialLayout>
</template>
<style scoped>
.profile-content{max-width:980px}.profile-password{display:flex;gap:6px;min-width:0}.profile-password .f-input{flex:1;min-width:0}.profile-link{text-decoration:underline;color:var(--f-brand)}#delete-account-error:focus{outline:3px solid var(--f-brand);outline-offset:3px}
</style>
