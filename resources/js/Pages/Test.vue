<script setup>
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { useDarkMode } from '@/Composables/useDarkMode';
import { SunIcon, MoonIcon } from '@heroicons/vue/24/outline';

const page = usePage();
const { isDark, toggleTheme } = useDarkMode();

const auth = computed(() => page.props.auth);
const company = computed(() => page.props.company);
const flash = computed(() => page.flash);
</script>

<template>
    <div class="min-h-screen bg-gray-50 dark:bg-slate-900 p-8 transition-colors duration-200">
        <div class="max-w-4xl mx-auto">
            <!-- Header with Dark Mode Toggle -->
            <div class="flex items-center justify-between mb-8">
                <h1 class="text-3xl font-bold text-gradient-primary">
                    Vue 3 + Inertia.js + Pinia
                </h1>

                <button
                    @click="toggleTheme"
                    class="btn btn-secondary"
                    type="button"
                >
                    <SunIcon v-if="isDark" class="w-5 h-5" />
                    <MoonIcon v-else class="w-5 h-5" />
                    {{ isDark ? 'Modo Claro' : 'Modo Oscuro' }}
                </button>
            </div>

            <!-- Design System Showcase -->
            <div class="card mb-6">
                <div class="card-header">
                    <h2 class="text-xl font-semibold text-gray-900 dark:text-white">
                        Sistema de Diseño
                    </h2>
                </div>
                <div class="card-body">
                    <div class="space-y-4">
                        <!-- Buttons -->
                        <div>
                            <h3 class="label">Botones:</h3>
                            <div class="flex flex-wrap gap-3">
                                <button class="btn btn-primary">Primary</button>
                                <button class="btn btn-secondary">Secondary</button>
                                <button class="btn btn-danger">Danger</button>
                                <button class="btn btn-ghost">Ghost</button>
                                <button class="btn btn-primary btn-sm">Small</button>
                                <button class="btn btn-primary" disabled>Disabled</button>
                            </div>
                        </div>

                        <!-- Badges -->
                        <div>
                            <h3 class="label">Badges:</h3>
                            <div class="flex flex-wrap gap-2">
                                <span class="badge badge-success">Success</span>
                                <span class="badge badge-warning">Warning</span>
                                <span class="badge badge-danger">Danger</span>
                                <span class="badge badge-info">Info</span>
                                <span class="badge badge-gray">Gray</span>
                            </div>
                        </div>

                        <!-- Alerts -->
                        <div>
                            <h3 class="label">Alerts:</h3>
                            <div class="space-y-3">
                                <div class="alert alert-success">
                                    <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                    </svg>
                                    <span>Operación exitosa</span>
                                </div>
                                <div class="alert alert-warning">
                                    <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                                    </svg>
                                    <span>Advertencia importante</span>
                                </div>
                            </div>
                        </div>

                        <!-- Inputs -->
                        <div>
                            <h3 class="label">Inputs:</h3>
                            <div class="space-y-3 max-w-md">
                                <input type="text" class="input" placeholder="Input normal">
                                <input type="text" class="input input-error" placeholder="Input con error">
                                <select class="select">
                                    <option>Opción 1</option>
                                    <option>Opción 2</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Props Tests -->
            <div class="space-y-6">
                <!-- Auth Test -->
                <div class="card animate-fade-in">
                    <div class="card-header">
                        <h2 class="text-xl font-semibold text-gray-900 dark:text-white">
                            Auth Props
                        </h2>
                    </div>
                    <div class="card-body">
                        <pre class="bg-gray-100 dark:bg-slate-900 p-4 rounded overflow-auto text-sm text-gray-900 dark:text-slate-100">{{ auth }}</pre>
                    </div>
                </div>

                <!-- Company Test -->
                <div class="card animate-fade-in">
                    <div class="card-header">
                        <h2 class="text-xl font-semibold text-gray-900 dark:text-white">
                            Company Props
                        </h2>
                    </div>
                    <div class="card-body">
                        <pre class="bg-gray-100 dark:bg-slate-900 p-4 rounded overflow-auto text-sm text-gray-900 dark:text-slate-100">{{ company }}</pre>
                    </div>
                </div>

                <!-- Flash Test -->
                <div class="card animate-fade-in">
                    <div class="card-header">
                        <h2 class="text-xl font-semibold text-gray-900 dark:text-white">
                            Flash Props
                        </h2>
                    </div>
                    <div class="card-body">
                        <pre class="bg-gray-100 dark:bg-slate-900 p-4 rounded overflow-auto text-sm text-gray-900 dark:text-slate-100">{{ flash }}</pre>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
