<script setup lang="ts">
import { computed } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import BrandMark from '@/Components/BrandMark.vue';
import FinancialField from '@/Components/UI/FinancialField.vue';
import { useRoute } from '@/Composables/useRoute';
import { focusInvalid } from '@/Composables/useFormFocus';
import { CheckCircleIcon, ExclamationTriangleIcon } from '@heroicons/vue/24/outline';
const props = defineProps<{ php: string; https: boolean; sqlite: boolean; mysql: boolean; codeFile: string }>();
const route = useRoute();
const form = useForm({ code: '', database: props.sqlite ? 'sqlite' : 'mysql', db_host: 'localhost', db_port: '3306', db_name: '', db_user: '', db_password: '',
    name: '', email: '', password: '', password_confirmation: '', demo: false });
// The server reports a failed installation outside any field.
const installError = computed(() => (form.errors as Record<string, string | undefined>).install);
const submit = () => form.post(route('installer.store'), { onFinish: () => { form.reset('password', 'password_confirmation', 'db_password'); void focusInvalid(); } });
</script>
<template>
    <Head title="Instalar CostoReal" />
    <div class="financial-shell f-auth-shell">
        <header class="f-auth-header"><span class="f-brand"><BrandMark /><span>CostoReal<small>Instalación</small></span></span></header>
        <main id="main-content" class="install-main" tabindex="-1">
            <p class="f-eyebrow">PRIMERA VEZ EN ESTE HOSTING</p>
            <h1>Instalar CostoReal</h1>
            <p class="f-hint install-lead">Unos minutos y queda lista. Todo se guarda en tu hosting: nadie más ve tus datos.</p>

            <section class="f-panel" aria-labelledby="hosting-heading"><div class="f-panel-heading"><h2 id="hosting-heading">Tu hosting</h2></div><ul class="f-panel-body install-checks">
                <li><CheckCircleIcon class="install-ok" /> PHP {{ php }}</li>
                <li v-if="https"><CheckCircleIcon class="install-ok" /> Conexión segura (HTTPS)</li>
                <li v-else><ExclamationTriangleIcon class="install-warn" /> Sin conexión segura. Activa el certificado SSL gratis en hPanel (Seguridad → SSL) antes de usarla con datos reales.</li>
                <li v-if="sqlite"><CheckCircleIcon class="install-ok" /> SQLite disponible: no hay que crear una base de datos</li>
                <li v-else><ExclamationTriangleIcon class="install-warn" /> SQLite no está activado: usa MySQL o activa pdo_sqlite en Configuración de PHP</li>
            </ul></section>

            <form @submit.prevent="submit" :aria-busy="form.processing" novalidate>
                <section class="f-panel" aria-labelledby="code-heading"><div class="f-panel-heading"><h2 id="code-heading">1 · Código de instalación</h2></div><div class="f-panel-body">
                    <FinancialField id="code" label="Código *" v-model="form.code" autocomplete="off" placeholder="XXXX-XXXX" :error="form.errors.code"
                        :help="`Ábrelo en hPanel → Administrador de archivos → ${codeFile}. Así nadie más puede instalarla antes que tú.`" />
                </div></section>

                <section class="f-panel" aria-labelledby="database-heading"><div class="f-panel-heading"><h2 id="database-heading">2 · Dónde guardar los datos</h2></div><div class="f-panel-body">
                    <label class="f-check"><input type="radio" value="sqlite" v-model="form.database" :disabled="!sqlite" /> Un archivo en tu hosting (SQLite). Recomendado: no hay nada que configurar.</label>
                    <label class="f-check" style="margin-top:10px"><input type="radio" value="mysql" v-model="form.database" :disabled="!mysql" /> Una base MySQL que creaste en hPanel (Bases de datos → MySQL).</label>
                    <p class="f-field-error" role="alert">{{ form.errors.database }}</p>
                    <div v-if="form.database === 'mysql'" class="f-form-grid" style="margin-top:16px">
                        <FinancialField id="db_host" label="Servidor *" v-model="form.db_host" :error="form.errors.db_host" help="En Hostinger suele ser localhost." />
                        <FinancialField id="db_port" label="Puerto *" v-model="form.db_port" inputmode="numeric" :error="form.errors.db_port" />
                        <FinancialField id="db_name" label="Nombre de la base *" v-model="form.db_name" :error="form.errors.db_name" help="Empieza con tu usuario de Hostinger, por ejemplo u123456789_costoreal." />
                        <FinancialField id="db_user" label="Usuario de la base *" v-model="form.db_user" :error="form.errors.db_user" />
                        <FinancialField id="db_password" label="Contraseña de la base" type="password" v-model="form.db_password" autocomplete="off" :error="form.errors.db_password" />
                    </div>
                </div></section>

                <section class="f-panel" aria-labelledby="account-heading"><div class="f-panel-heading"><h2 id="account-heading">3 · Tu cuenta</h2><span class="f-hint">Será la dueña de la instalación</span></div><div class="f-panel-body f-form-grid">
                    <FinancialField id="name" label="Nombre *" v-model="form.name" autocomplete="name" :error="form.errors.name" />
                    <FinancialField id="email" label="Correo electrónico *" type="email" v-model="form.email" autocomplete="email" :error="form.errors.email" />
                    <FinancialField id="password" label="Contraseña *" type="password" v-model="form.password" autocomplete="new-password" :error="form.errors.password" help="Al menos 10 caracteres, con letras y números." />
                    <FinancialField id="password_confirmation" label="Repite la contraseña *" type="password" v-model="form.password_confirmation" autocomplete="new-password" :error="form.errors.password_confirmation" />
                </div></section>

                <section class="f-panel"><div class="f-panel-body">
                    <label class="f-check"><input type="checkbox" v-model="form.demo" /> Cargar datos de demostración: un viaje de compras a Brasil y una importación, ambos ficticios. Puedes borrarlos después.</label>
                </div></section>

                <div v-if="installError" class="f-notice f-notice-error" role="alert">{{ installError }}</div>
                <div class="f-actions"><button type="submit" class="f-button f-button-primary" :disabled="form.processing">{{ form.processing ? 'Instalando…' : 'Instalar CostoReal' }}</button></div>
                <p class="f-hint" style="margin-top:12px">Después de instalar, nadie más puede crear cuentas. Si quieres invitar a alguien, lo activas en tu perfil.</p>
            </form>
        </main>
    </div>
</template>
<style scoped>
.install-main{width:100%;max-width:760px;margin:0 auto;padding:10px 20px 60px}
.install-main h1{font-size:30px;font-weight:650;letter-spacing:-.5px;margin:8px 0 6px}
.install-lead{font-size:14px;margin-bottom:22px}
.install-main .f-panel{margin-bottom:18px}
.install-checks{list-style:none;display:grid;gap:10px;margin:0}
.install-checks li{display:flex;gap:10px;align-items:flex-start}
.install-checks svg{width:20px;height:20px;flex:none}
.install-ok{color:var(--f-brand)}
.install-warn{color:var(--f-danger)}
</style>
