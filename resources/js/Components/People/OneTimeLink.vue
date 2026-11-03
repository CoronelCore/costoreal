<script setup lang="ts">
import { computed, ref } from "vue";
import type { SharedLink } from "@/Types/context";

const props = defineProps<{ link: SharedLink }>();
const copied = ref(false);
const firstName = computed(() => props.link.name.split(" ")[0]);
const title = computed(() =>
    props.link.kind === "invitation"
        ? `Invitación para ${props.link.name}`
        : `Enlace para que ${props.link.name} cree una contraseña nueva`,
);
const message = computed(() =>
    props.link.kind === "invitation"
        ? `Hola ${firstName.value}, te invito a probar CostoReal, la app con la que calculo el costo real de lo que importo. Crea tu cuenta aquí (el enlace vence en ${props.link.hours} horas): ${props.link.url}`
        : `Hola ${firstName.value}, con este enlace creas una contraseña nueva para CostoReal (vence en ${props.link.hours} horas): ${props.link.url}`,
);
const whatsapp = computed(
    () => `https://wa.me/?text=${encodeURIComponent(message.value)}`,
);
async function copy() {
    try {
        await navigator.clipboard.writeText(props.link.url);
        copied.value = true;
        setTimeout(() => (copied.value = false), 2500);
    } catch {
        document.getElementById("one-time-link")?.focus();
    }
}
</script>
<template>
    <section
        class="f-panel one-time-link"
        aria-labelledby="one-time-link-heading"
    >
        <div class="f-panel-heading">
            <h2 id="one-time-link-heading">{{ title }}</h2>
            <span class="f-status">Vence en {{ link.hours }} horas</span>
        </div>
        <div class="f-panel-body">
            <p>
                Mándaselo por WhatsApp o por donde prefieras. Sirve una sola
                vez y no se vuelve a mostrar: cópialo ahora.
            </p>
            <div class="one-time-link-row">
                <label class="sr-only" for="one-time-link">Enlace</label>
                <input
                    id="one-time-link"
                    class="f-input"
                    :value="link.url"
                    readonly
                    @focus="($event.target as HTMLInputElement).select()"
                />
                <button class="f-button" type="button" @click="copy">
                    {{ copied ? "Copiado" : "Copiar" }}
                </button>
                <a
                    class="f-button f-button-primary"
                    :href="whatsapp"
                    target="_blank"
                    rel="noopener noreferrer"
                    >Enviar por WhatsApp</a
                >
            </div>
            <p class="f-hint" role="status" aria-live="polite">
                {{ copied ? "Enlace copiado." : "" }}
            </p>
        </div>
    </section>
</template>
<style scoped>
.one-time-link {
    border-color: var(--f-brand);
}
.one-time-link-row {
    display: flex;
    gap: 8px;
    margin-top: 14px;
    flex-wrap: wrap;
}
.one-time-link-row .f-input {
    flex: 1 1 260px;
    min-width: 0;
    font-family: ui-monospace, monospace;
    font-size: 12px;
}
</style>
