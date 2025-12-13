import { ref } from 'vue';

/**
 * Composable for confirmation dialogs
 *
 * @returns {Object} - State and methods for confirmation dialog
 */
export function useConfirm() {
    const isOpen = ref(false);
    const message = ref('');
    const title = ref('');
    let onConfirmCallback = null;
    let onCancelCallback = null;

    /**
     * Show confirmation dialog
     *
     * @param {Object} options - Confirmation options
     * @param {string} options.message - Confirmation message
     * @param {string} options.title - Dialog title (optional)
     * @param {Function} options.onConfirm - Callback when confirmed
     * @param {Function} options.onCancel - Callback when cancelled (optional)
     */
    const show = ({ message: msg, title: tit = 'Confirmar acción', onConfirm, onCancel }) => {
        message.value = msg;
        title.value = tit;
        onConfirmCallback = onConfirm;
        onCancelCallback = onCancel;
        isOpen.value = true;
    };

    /**
     * Confirm action
     */
    const confirm = () => {
        if (onConfirmCallback) {
            onConfirmCallback();
        }
        close();
    };

    /**
     * Cancel action
     */
    const cancel = () => {
        if (onCancelCallback) {
            onCancelCallback();
        }
        close();
    };

    /**
     * Close dialog
     */
    const close = () => {
        isOpen.value = false;
        message.value = '';
        title.value = '';
        onConfirmCallback = null;
        onCancelCallback = null;
    };

    return {
        isOpen,
        message,
        title,
        show,
        confirm,
        cancel,
        close,
    };
}
