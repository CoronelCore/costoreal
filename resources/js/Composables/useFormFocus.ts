import { nextTick } from "vue";
export const focusInvalid = async () => {
    await nextTick();
    document.querySelector<HTMLElement>('main [aria-invalid="true"]')?.focus();
};
