import { ref } from 'vue';

// Global toast state
const toasts = ref([]);
let nextId = 1;

export function useToast() {
    /**
     * Show a toast notification
     * @param {Object} options - Toast options
     * @param {string} options.message - Toast message
     * @param {string} options.type - Toast type (success, error, warning, info)
     * @param {number} options.duration - Duration in ms (0 = no auto-dismiss)
     */
    const show = ({ message, type = 'info', duration = 5000 }) => {
        const id = nextId++;

        const toast = {
            id,
            message,
            type,
        };

        toasts.value.push(toast);

        // Auto-dismiss if duration > 0
        if (duration > 0) {
            setTimeout(() => {
                remove(id);
            }, duration);
        }

        return id;
    };

    /**
     * Remove a specific toast
     * @param {number} id - Toast ID
     */
    const remove = (id) => {
        const index = toasts.value.findIndex(toast => toast.id === id);
        if (index > -1) {
            toasts.value.splice(index, 1);
        }
    };

    /**
     * Convenience methods for different toast types
     */
    const success = (message, duration = 5000) => {
        return show({ message, type: 'success', duration });
    };

    const error = (message, duration = 5000) => {
        return show({ message, type: 'error', duration });
    };

    const warning = (message, duration = 5000) => {
        return show({ message, type: 'warning', duration });
    };

    const info = (message, duration = 5000) => {
        return show({ message, type: 'info', duration });
    };

    /**
     * Clear all toasts
     */
    const clear = () => {
        toasts.value = [];
    };

    return {
        toasts,
        show,
        remove,
        success,
        error,
        warning,
        info,
        clear,
    };
}
