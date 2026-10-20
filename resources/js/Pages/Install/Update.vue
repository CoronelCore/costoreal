<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import BrandMark from '@/Components/BrandMark.vue';
import { useRoute } from '@/Composables/useRoute';
defineProps<{ pending: number; signedIn: boolean; isOwner: boolean }>();
const route = useRoute();
const form = useForm({});
const apply = () => form.post(route('installer.apply'));
</script>
<template>
    <Head title="Actualizar CostoReal" />
    <div class="financial-shell f-auth-shell">
        <header class="f-auth-header"><span class="f-brand"><BrandMark /><span>CostoReal<small>Actualización</small></span></span></header>
        <main id="main-content" class="update-main" tabindex="-1">
            <p class="f-eyebrow">VERSIÓN NUEVA SUBIDA</p>
            <h1>Falta aplicar la actualización</h1>
            <p class="f-hint update-lead">Subiste una versión nueva de CostoReal. Para usarla hay que preparar la base de datos ({{ pending }} {{ pending === 1 ? 'cambio' : 'cambios' }}). Tus datos se conservan.</p>
            <section class="f-panel"><div class="f-panel-body">
                <template v-if="!signedIn"><p>Entra con la cuenta dueña de esta instalación para aplicarla.</p><div class="f-actions" style="margin-top:16px"><Link :href="route('login')" class="f-button f-button-primary">Iniciar sesión</Link></div></template>
                <template v-else-if="!isOwner"><p>Solo la cuenta dueña de esta instalación puede aplicar la actualización. Avísale a quien la administra.</p></template>
                <template v-else><p>Antes de aplicarla se guarda una copia de la base de datos en tu hosting, por si necesitas volver atrás.</p><div class="f-actions" style="margin-top:16px"><button class="f-button f-button-primary" :disabled="form.processing" @click="apply">{{ form.processing ? 'Aplicando…' : 'Aplicar actualización' }}</button></div></template>
            </div></section>
        </main>
    </div>
</template>
<style scoped>
.update-main{width:100%;max-width:640px;margin:0 auto;padding:10px 20px 60px}
.update-main h1{font-size:28px;font-weight:650;letter-spacing:-.5px;margin:8px 0 6px}
.update-lead{font-size:14px;margin-bottom:22px}
</style>
