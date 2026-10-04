import { ref, watch, onMounted, onUnmounted } from 'vue';
import { router } from '@inertiajs/vue3';

export function useTabs(storageKey, defaultTab, allowedTabs = [defaultTab]) {
    const activeTab = ref(defaultTab);
    const valid = (id) => allowedTabs.includes(id);
    let removeNavigateListener;
    let initialPath;
    const persistHash = () => window.history.replaceState(window.history.state, '', `${window.location.pathname}${window.location.search}#${activeTab.value}`);
    const readHash = () => window.location.hash.slice(1);
    const handleHashChange = () => {
        const hash = readHash();
        activeTab.value = valid(hash) ? hash : defaultTab;
    };
    const setTab = (id) => { if (valid(id)) activeTab.value = id; };
    watch(activeTab, (id) => {
        if (typeof window === 'undefined') return;
        window.history.replaceState(window.history.state, '', `${window.location.pathname}${window.location.search}#${id}`);
        try { window.localStorage.setItem(storageKey, id); } catch { /* Storage may be unavailable. */ }
    });
    onMounted(() => {
        let stored;
        try { stored = window.localStorage.getItem(storageKey); } catch { /* Use default. */ }
        const hash = readHash();
        activeTab.value = valid(hash) ? hash : valid(stored) ? stored : defaultTab;
        window.addEventListener('hashchange', handleHashChange);
        initialPath = window.location.pathname;
        removeNavigateListener = router.on('finish', () => {
            if (window.location.pathname !== initialPath) return;
            const hash = readHash();
            if (valid(hash)) activeTab.value = hash;
            else persistHash();
        });
    });
    const cleanup = () => { window.removeEventListener('hashchange', handleHashChange); removeNavigateListener?.(); };
    onUnmounted(cleanup);
    return { activeTab, setTab, cleanup };
}
