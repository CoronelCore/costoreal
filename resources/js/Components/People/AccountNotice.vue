<script setup lang="ts">
// Shown before an invitation or an open registration creates an account: where the data lives and who can reach it.
defineProps<{
    owner: string;
    beta: boolean;
    error?: string;
    disabled?: boolean;
}>();
const understood = defineModel<boolean>({ required: true });
</script>
<template>
    <section class="account-notice" aria-labelledby="notice-heading">
        <h2 id="notice-heading">Antes de crear tu cuenta</h2>
        <ul>
            <li v-if="beta">
                Es una versión de prueba: puede tener errores y cambiar mientras
                {{ owner }} la mejora con lo que le cuentes.
            </li>
            <li>Lo que registres lo ves solo tú dentro de la app.</li>
            <li>
                Los datos quedan en el hosting de {{ owner }}. Como lo
                administra, puede llegar a la base de datos, y sus respaldos
                incluyen los datos de todas las cuentas.
            </li>
            <li>Si son datos delicados, prueba con montos de ejemplo.</li>
            <li>
                Si ya no quieres usarla, avísale a {{ owner }}: puede quitarte
                el acceso.
            </li>
        </ul>
        <label class="f-check"
            ><input
                id="understood"
                type="checkbox"
                v-model="understood"
                :disabled="disabled"
                :aria-invalid="!!error"
                aria-describedby="understood-error"
            />
            Entendí cómo se guardan mis datos</label
        >
        <p id="understood-error" v-if="error" class="f-field-error">
            {{ error }}
        </p>
    </section>
</template>
<style scoped>
.account-notice {
    border: 1px solid var(--f-line);
    border-radius: 10px;
    background: var(--f-subtle);
    padding: 14px 16px;
}
.account-notice h2 {
    font-size: 14px;
    font-weight: 600;
    margin: 0 0 8px;
}
.account-notice ul {
    margin: 0 0 12px;
    padding-left: 18px;
    list-style: disc;
    display: grid;
    gap: 6px;
    font-size: 13px;
    color: var(--f-muted);
}
</style>
