import { computed } from 'vue';
import { useThemeStore } from '@/Stores/theme';

/**
 * Composable for managing dark mode
 * Provides easy access to theme state and toggle functionality
 *
 */
export function useDarkMode() {
    const themeStore = useThemeStore();

    // Computed property for reactive dark mode state
    const isDark = computed(() => themeStore.isDark);

    // Toggle between light and dark mode
    const toggleTheme = () => {
        themeStore.toggleTheme();
    };

    // Set theme explicitly (true for dark, false for light)
    const setTheme = (dark: boolean) => {
        themeStore.setTheme(dark);
    };

    return {
        isDark,
        toggleTheme,
        setTheme,
    };
}
