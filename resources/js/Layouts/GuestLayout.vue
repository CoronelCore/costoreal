<script setup>
import { Link } from '@inertiajs/vue3';
import { useDarkMode } from '@/Composables/useDarkMode';
import { CurrencyDollarIcon, SunIcon, MoonIcon } from '@heroicons/vue/24/outline';

const { isDark, toggleTheme } = useDarkMode();

const currentYear = new Date().getFullYear();
</script>

<template>
    <div
        class="min-h-screen flex flex-col transition-colors duration-200"
        :style="{
            backgroundColor: isDark ? '#0f172a' : '#f9fafb',
            backgroundImage: isDark
                ? 'radial-gradient(at 0% 0%, rgba(59, 130, 246, 0.15) 0%, transparent 50%), radial-gradient(at 100% 100%, rgba(16, 185, 129, 0.15) 0%, transparent 50%)'
                : 'radial-gradient(at 0% 0%, rgba(59, 130, 246, 0.1) 0%, transparent 50%), radial-gradient(at 100% 100%, rgba(16, 185, 129, 0.1) 0%, transparent 50%)'
        }"
    >
        <!-- Dark Mode Toggle (top right) -->
        <div class="absolute top-4 right-4 z-10">
            <button
                @click="toggleTheme"
                class="p-2 rounded-lg text-gray-600 hover:text-gray-900 hover:bg-white/50 dark:text-slate-400 dark:hover:text-slate-100 dark:hover:bg-slate-800/50 transition-all duration-300 backdrop-blur-sm shadow-md"
                type="button"
                title="Cambiar tema"
            >
                <SunIcon v-if="isDark" class="h-5 w-5" />
                <MoonIcon v-else class="h-5 w-5" />
            </button>
        </div>

        <!-- Main Content Container -->
        <div class="flex-1 flex flex-col items-center justify-center py-12 px-4 sm:px-6 lg:px-8">
            <!-- Logo -->
            <div class="mb-8 text-center">
                <Link href="/" class="inline-flex flex-col items-center gap-3 group">
                    <div class="bg-gradient-to-br from-primary-500 to-primary-600 p-4 rounded-2xl shadow-xl group-hover:shadow-2xl transition-all duration-300 group-hover:scale-105">
                        <CurrencyDollarIcon class="h-12 w-12 text-white animate-coin-flip" />
                    </div>
                    <h1 class="text-3xl font-bold text-gradient-primary">
                        CostoReal
                    </h1>
                    <p class="text-sm text-gray-600 dark:text-slate-400">
                        Sistema de Gestión de Costos de Importación
                    </p>
                </Link>
            </div>

            <!-- Card with Form -->
            <div class="w-full max-w-md">
                <div class="card-glass shadow-xl animate-fade-in-up">
                    <div class="card-body">
                        <slot />
                    </div>
                </div>
            </div>
        </div>

        <!-- Footer -->
        <footer class="py-6 text-center text-sm text-gray-500 dark:text-slate-500">
            <p>&copy; {{ currentYear }} CostoReal. Todos los derechos reservados.</p>
        </footer>
    </div>
</template>
