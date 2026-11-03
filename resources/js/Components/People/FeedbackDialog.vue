<script setup lang="ts">
import { useForm, usePage } from "@inertiajs/vue3";
import Modal from "@/Components/UI/Modal.vue";
import { useRoute } from "@/Composables/useRoute";
import { currentScreen } from "@/Utils/screens";

defineProps<{ show: boolean; owner: string | null }>();
const emit = defineEmits<{ close: [] }>();
const route = useRoute();
const page = usePage();
const form = useForm({
    message: "",
    screen: "",
    viewport_width: 0 as number | null,
});
function close() {
    if (form.processing) return;
    form.clearErrors();
    emit("close");
}
function send() {
    form.screen = currentScreen(page.component, window.location.hash);
    form.viewport_width = Math.round(window.innerWidth) || null;
    form.post(route("feedback.store"), {
        preserveScroll: true,
        onSuccess: () => {
            form.reset();
            emit("close");
        },
    });
}
</script>
<template>
    <Modal :show="show" title="Enviar comentario" :closeable="!form.processing" @close="close">
        <form id="feedback-form" class="f-field" @submit.prevent="send">
            <label for="feedback-message"
                >¿Qué estabas haciendo y qué esperabas que pasara?</label
            >
            <textarea
                id="feedback-message"
                class="f-input"
                v-model="form.message"
                rows="6"
                maxlength="2000"
                required
                :disabled="form.processing"
                :aria-invalid="!!form.errors.message"
                aria-describedby="feedback-help feedback-error"
            ></textarea>
            <p id="feedback-help" class="f-hint">
                También sirven ideas y cosas que te gustaron. Le llega a
                {{ owner || "quien administra CostoReal" }} con el tipo de
                pantalla en que estás, sin tus datos.
            </p>
            <p id="feedback-error" v-if="form.errors.message" class="f-field-error">
                {{ form.errors.message }}
            </p>
        </form>
        <template #footer
            ><div class="f-actions">
                <button class="f-button" type="button" :disabled="form.processing" @click="close">
                    Cancelar</button
                ><button
                    type="submit"
                    form="feedback-form"
                    class="f-button f-button-primary"
                    :disabled="form.processing || !form.message.trim()"
                >
                    {{ form.processing ? "Enviando…" : "Enviar" }}
                </button>
            </div></template
        >
    </Modal>
</template>
