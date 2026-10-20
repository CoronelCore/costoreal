<script setup lang="ts">
import { nextTick, ref } from 'vue';
import { Head } from '@inertiajs/vue3';
import FinancialLayout from '@/Layouts/FinancialLayout.vue';
import FinancialField from '@/Components/UI/FinancialField.vue';
import Modal from '@/Components/UI/Modal.vue';
import { formatDateOnly, formatMoney } from '@/Utils/format';
const name = ref('');
const note = ref('');
const amount = ref('1250.50');
const date = ref('2026-10-03');
const selected = ref('activo');
const checked = ref(false);
const error = ref('');
const success = ref(false);
const dialog = ref(false);
const cancel = ref<HTMLButtonElement | null>(null);
const showDialog = (event: Event) => {
    if (event.currentTarget instanceof HTMLElement) event.currentTarget.focus();
    dialog.value = true;
};
const validate = async () => {
    error.value = name.value.trim() ? '' : 'Completa el nombre antes de continuar.';
    success.value = !error.value;
    if (error.value) { await nextTick(); document.getElementById('design-name')?.focus(); }
};
const colors = [
    ['Fondo', '--f-bg'], ['Superficie', '--f-surface'], ['Superficie secundaria', '--f-subtle'],
    ['Texto principal', '--f-ink'], ['Texto secundario', '--f-muted'], ['Acción y resultado positivo', '--f-brand'],
    ['Error y pérdida', '--f-danger'], ['Advertencia', '--f-warning'], ['Límite de campo', '--f-control-line'],
];
</script>
<template>
    <Head title="Guía visual" />
    <FinancialLayout>
        <div class="f-heading"><div><div class="f-eyebrow">COSTOREAL · GUÍA VISUAL</div><h1>Claridad para decidir</h1><p>Tipografía, controles y estados de la interfaz financiera.</p></div></div>
        <p class="f-notice">Cambia el tema y la densidad desde la barra superior. Estos ejemplos permiten revisar la interfaz sin modificar tus importaciones.</p>
        <div class="f-metrics">
            <article class="f-metric f-metric-accent"><span class="f-metric-label">Inversión · CLP</span><strong class="f-metric-value">${{ formatMoney('1250500') }}</strong><small>Totales en pesos completos</small></article>
            <article class="f-metric"><span class="f-metric-label">Costo unitario · CLP</span><strong class="f-metric-value">${{ formatMoney('1250.50', 2) }}</strong><small>Precisión visible de dos decimales</small></article>
            <article class="f-metric"><span class="f-metric-label">Utilidad proyectada · CLP</span><strong class="f-metric-value f-positive">${{ formatMoney('82500') }}</strong><small>Proyección del lote completo</small></article>
            <article class="f-metric"><span class="f-metric-label">Pérdida proyectada · CLP</span><strong class="f-metric-value f-negative">${{ formatMoney('-24500') }}</strong><small>Signo y texto acompañan el color</small></article>
        </div>
        <div class="f-design-grid">
            <section class="f-panel"><div class="f-panel-heading"><h2>Paleta y jerarquía</h2></div><div class="f-panel-body"><div v-for="[label, token] in colors" :key="token" class="f-design-swatch"><span :style="{background:`var(${token})`}" aria-hidden="true"></span><div>{{ label }}</div></div><h3 style="font-size:20px;font-weight:650;margin-top:24px">Una compra, un costo explicable</h3><p style="margin-top:8px">La información principal aparece primero; el detalle y su evidencia se encuentran al avanzar.</p><p class="f-hint" style="margin-top:8px">Texto de apoyo · fecha {{ formatDateOnly(date) }}</p></div></section>
            <section class="f-panel"><div class="f-panel-heading"><h2>Acciones y estados</h2></div><div class="f-panel-body"><div class="f-actions"><button class="f-button f-button-primary" @click="showDialog">Abrir confirmación</button><button class="f-button" @click="success=false; error=''; name=''">Limpiar ejemplo</button><button class="f-button f-button-danger" @click="error='Este ejemplo muestra cómo recuperar un error.'; success=false">Mostrar error</button><button class="f-button" disabled>Guardando…</button></div><div class="f-actions" style="margin:20px 0"><span class="f-status">En curso</span><span class="f-status f-status-closed">Cerrada</span><span class="f-status f-status-warning">Precio pendiente</span></div><p class="f-notice f-notice-success">Cambios guardados. El historial conserva la revisión anterior.</p><p class="f-notice f-notice-error">No se guardaron los cambios. Revisa el campo señalado y vuelve a intentar.</p><p class="f-notice">La proyección contiene líneas sin precio. Completa sus precios para evaluar todo el lote.</p><p class="f-hint">Las acciones pueden recorrerse con Tab. Escape cierra el diálogo y devuelve el foco al botón que lo abrió.</p></div></section>
        </div>
        <section class="f-panel"><div class="f-panel-heading"><h2>Formulario y recuperación</h2></div><form class="f-panel-body f-editor-form" @submit.prevent="validate"><div class="f-form-grid"><FinancialField id="design-name" label="Nombre de la importación *" v-model="name" :error="error" help="Usa un nombre que reconozcas al buscar." placeholder="Ej. Compra de octubre" maxlength="255" /><FinancialField id="design-amount" label="Costo unitario · CLP" v-model="amount" inputmode="decimal" help="Los importes conservan su precisión." /><FinancialField id="design-date" label="Fecha de la compra" v-model="date" type="date" /><div class="f-field"><label for="design-state">Estado</label><select id="design-state" class="f-input" v-model="selected"><option value="activo">En curso</option><option value="finalizado">Cerrada</option></select></div><FinancialField id="design-note" label="Descripción" v-model="note" multiline rows="3" help="Información opcional que acompaña la compra." /><div class="f-field"><span>Preferencia opcional</span><label class="f-check" for="design-check"><input id="design-check" type="checkbox" v-model="checked" /> Conservar esta preferencia en el ejemplo</label></div></div><p v-if="success" class="f-notice f-notice-success" role="status" style="margin-top:20px">Ejemplo validado. Puedes cambiar los datos y volver a probar.</p><div class="f-actions" style="margin-top:20px"><button class="f-button f-button-primary" type="submit">Probar validación</button></div></form></section>
        <section class="f-panel"><div class="f-panel-heading"><h2>Tabla y lectura en móvil</h2></div><div class="f-table-scroll f-desktop-table"><table class="f-table"><caption class="sr-only">Ejemplos de costos y proyecciones</caption><thead><tr><th scope="col">Producto</th><th scope="col">Estado</th><th scope="col" class="f-numeric">Costo · CLP</th><th scope="col" class="f-numeric">Utilidad proyectada · CLP</th></tr></thead><tbody><tr><td><strong class="f-table-title">Herramienta de precisión para diagnóstico y reparación de equipos</strong><span class="f-table-subtitle">Nombre largo · trazabilidad disponible</span></td><td><span class="f-status">Conciliado</span></td><td class="f-numeric">$1.250,50</td><td class="f-numeric f-positive">$8.250</td></tr><tr><td><strong class="f-table-title">Repuesto con pérdida proyectada</strong></td><td><span class="f-status f-status-warning">Revisar precio</span></td><td class="f-numeric">$4.000</td><td class="f-numeric f-negative">-$1.250</td></tr></tbody></table></div><div class="f-mobile-cards"><article class="f-mobile-card"><strong class="f-table-title">Herramienta de precisión para diagnóstico y reparación de equipos</strong><span class="f-status" style="margin-top:10px">Conciliado</span><div class="f-mobile-card-bottom"><span>Costo · CLP</span><strong>$1.250,50</strong></div><div class="f-mobile-card-bottom"><span>Utilidad proyectada</span><strong class="f-positive">$8.250</strong></div></article><article class="f-mobile-card"><strong class="f-table-title">Repuesto con pérdida proyectada</strong><div class="f-mobile-card-bottom"><span>Utilidad proyectada</span><strong class="f-negative">-$1.250</strong></div></article></div></section>
        <section class="f-panel"><div class="f-empty"><h2>Sin resultados para este filtro</h2><p>Prueba otro nombre, período o estado para encontrar tus importaciones.</p><button class="f-button" @click="name=''; error=''; success=false">Limpiar filtros del ejemplo</button></div></section>
        <Modal :show="dialog" title="Confirmar cambio" :initial-focus="cancel ?? undefined" @close="dialog=false"><p>La confirmación explica el efecto de la acción antes de continuar. Este ejemplo no realiza cambios en tus compras.</p><template #footer><div class="f-actions"><button ref="cancel" class="f-button" @click="dialog=false">Cancelar</button><button class="f-button f-button-primary" @click="dialog=false">Entendido</button></div></template></Modal>
    </FinancialLayout>
</template>
