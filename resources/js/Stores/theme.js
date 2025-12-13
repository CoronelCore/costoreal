import { defineStore } from 'pinia';
import { ref, watch } from 'vue';

export const useThemeStore = defineStore('theme', () => {
    // State: Dark mode is enabled or not
    const isDark = ref(false);

    // Initialize from localStorage on store creation
    const initTheme = () => {
        // Check localStorage first
        const stored = localStorage.getItem('theme');

        if (stored) {
            isDark.value = stored === 'dark';
        } else {
            // Fallback to system preference
            isDark.value = window.matchMedia('(prefers-color-scheme: dark)').matches;
        }

        // Apply the theme
        applyTheme();
    };

    // Apply theme to DOM
    const applyTheme = () => {
        if (isDark.value) {
            document.documentElement.classList.add('dark');
        } else {
            document.documentElement.classList.remove('dark');
        }
    };

    // Toggle theme
    const toggleTheme = () => {
        isDark.value = !isDark.value;
    };

    // Set theme explicitly
    const setTheme = (dark) => {
        isDark.value = dark;
    };

    // Watch for changes and persist + apply
    watch(isDark, (newValue) => {
        localStorage.setItem('theme', newValue ? 'dark' : 'light');
        applyTheme();
    });

    // Initialize theme on store creation
    initTheme();

    return {
        isDark,
        toggleTheme,
        setTheme,
    };
});
