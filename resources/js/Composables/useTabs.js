import { ref, watch, onMounted } from 'vue';

/**
 * Composable for managing tabs with URL hash and localStorage persistence
 *
 * @param {string} storageKey - Key for localStorage persistence
 * @param {string} defaultTab - Default tab ID
 * @returns {Object} - activeTab (ref) and setTab (function)
 */
export function useTabs(storageKey, defaultTab) {
    const activeTab = ref(defaultTab);

    // Initialize from URL hash, then localStorage, then default
    const initializeTab = () => {
        // Priority 1: URL hash
        const hash = window.location.hash.substring(1);
        if (hash) {
            activeTab.value = hash;
            return;
        }

        // Priority 2: localStorage
        const stored = localStorage.getItem(storageKey);
        if (stored) {
            activeTab.value = stored;
            return;
        }

        // Priority 3: default
        activeTab.value = defaultTab;
    };

    // Set active tab
    const setTab = (tabId) => {
        activeTab.value = tabId;
    };

    // Watch activeTab and update URL hash + localStorage
    watch(activeTab, (newValue) => {
        // Update URL hash without page reload
        window.location.hash = newValue;

        // Persist to localStorage
        localStorage.setItem(storageKey, newValue);
    });

    // Listen for hash changes (browser back/forward navigation)
    const handleHashChange = () => {
        const hash = window.location.hash.substring(1);
        if (hash && hash !== activeTab.value) {
            activeTab.value = hash;
        }
    };

    // Setup on mounted
    onMounted(() => {
        initializeTab();
        window.addEventListener('hashchange', handleHashChange);
    });

    // Cleanup is handled automatically by Vue's onUnmounted if needed
    // But we can also return a cleanup function if the user wants manual control
    const cleanup = () => {
        window.removeEventListener('hashchange', handleHashChange);
    };

    return {
        activeTab,
        setTab,
        cleanup,
    };
}
