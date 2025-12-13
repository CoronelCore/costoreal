<script setup>
import { ref, computed, inject, onMounted, onUnmounted } from 'vue';
import { Link, usePage, router } from '@inertiajs/vue3';
import { useDarkMode } from '@/Composables/useDarkMode';
import Dropdown from '@/Components/UI/Dropdown.vue';
import Toast from '@/Components/UI/Toast.vue';
import {
    Bars3Icon,
    XMarkIcon,
    SunIcon,
    MoonIcon,
    BuildingOfficeIcon,
    ChartBarIcon,
    CurrencyDollarIcon,
    UserCircleIcon,
    ArrowRightOnRectangleIcon,
    Cog6ToothIcon,
    CheckIcon,
} from '@heroicons/vue/24/outline';
import { MenuItem } from '@headlessui/vue';

// Ziggy route helper
const route = inject('route');

const page = usePage();
const { isDark, toggleTheme } = useDarkMode();
const showMobileMenu = ref(false);
const isScrolled = ref(false);

const auth = computed(() => page.props.auth);
const company = computed(() => page.props.company);

const userInitial = computed(() => {
    return auth.value.user?.name?.charAt(0).toUpperCase() || 'U';
});

const logout = () => {
    router.post(route('logout'));
};

const switchCompany = (companyId) => {
    router.get(route('company.switch', companyId || 'all'), {}, {
        preserveState: false,
    });
};

// Handle scroll effect for navbar
const handleScroll = () => {
    isScrolled.value = window.scrollY > 10;
};

onMounted(() => {
    window.addEventListener('scroll', handleScroll);
});

onUnmounted(() => {
    window.removeEventListener('scroll', handleScroll);
});
</script>

<template>
    <div class="min-h-screen bg-gray-50 dark:bg-slate-900 transition-colors duration-200">
        <!-- Navigation -->
        <nav
            :class="[
                'sticky top-0 z-40 bg-white/80 dark:bg-slate-900/80 backdrop-blur-md border-b border-gray-200 dark:border-slate-700 transition-all duration-300',
                isScrolled ? 'shadow-md' : 'shadow-sm'
            ]"
        >
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex justify-between h-16">
                    <!-- Left side: Logo and main navigation -->
                    <div class="flex">
                        <!-- Logo -->
                        <div class="flex-shrink-0 flex items-center">
                            <Link :href="route('dashboard')" class="flex items-center gap-2 group">
                                <div class="p-2 rounded-lg bg-gradient-to-br from-primary-500 to-primary-600 shadow-lg group-hover:shadow-primary-500/30 transition-all duration-200 group-hover:scale-105">
                                    <CurrencyDollarIcon class="h-6 w-6 text-white" />
                                </div>
                                <span class="text-xl font-bold text-gray-900 dark:text-white hidden sm:block">
                                    CostoReal
                                </span>
                            </Link>
                        </div>

                        <!-- Desktop Navigation Links -->
                        <div class="hidden sm:ml-8 sm:flex sm:space-x-2">
                            <Link
                                :href="route('trips.index')"
                                :class="[
                                    'inline-flex items-center gap-2 px-4 py-2 rounded-lg font-medium transition-all duration-200',
                                    route().current('trips.*') 
                                        ? 'bg-primary-50 dark:bg-primary-900/20 text-primary-600 dark:text-primary-400' 
                                        : 'text-gray-600 dark:text-slate-400 hover:bg-gray-50 dark:hover:bg-slate-800 hover:text-gray-900 dark:hover:text-white'
                                ]"
                            >
                                <CurrencyDollarIcon class="h-5 w-5" />
                                Importaciones
                            </Link>

                            <Link
                                :href="route('analytics.index')"
                                :class="[
                                    'inline-flex items-center gap-2 px-4 py-2 rounded-lg font-medium transition-all duration-300',
                                    route().current('analytics.*') ? 'nav-link-active dark:text-primary-400 dark:bg-primary-500/10 dark:shadow-neon-primary' : 'nav-link'
                                ]"
                            >
                                <ChartBarIcon class="h-5 w-5" />
                                Analítica
                            </Link>
                        </div>
                    </div>

                    <!-- Right side: Dark mode, Companies, User -->
                    <div class="flex items-center gap-2">
                        <!-- Dark Mode Toggle -->
                        <button
                            @click="toggleTheme"
                            class="p-2 rounded-lg text-gray-600 hover:text-gray-900 hover:bg-gray-100 dark:text-slate-400 dark:hover:text-slate-100 dark:hover:bg-slate-700 transition-all duration-300 hover:scale-110 active:scale-95"
                            type="button"
                            title="Cambiar tema"
                        >
                            <Transition
                                enter-active-class="transition-all duration-300"
                                enter-from-class="opacity-0 rotate-90 scale-50"
                                enter-to-class="opacity-100 rotate-0 scale-100"
                                leave-active-class="transition-all duration-300"
                                leave-from-class="opacity-100 rotate-0 scale-100"
                                leave-to-class="opacity-0 -rotate-90 scale-50"
                                mode="out-in"
                            >
                                <SunIcon v-if="isDark" key="sun" class="h-5 w-5" />
                                <MoonIcon v-else key="moon" class="h-5 w-5" />
                            </Transition>
                        </button>

                        <!-- Companies Dropdown -->
                        <Dropdown align="right" width="56">
                            <template #trigger>
                                <button
                                    class="flex items-center gap-2 px-3 py-2 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-100 dark:text-slate-300 dark:hover:bg-slate-700 transition-colors"
                                    type="button"
                                >
                                    <BuildingOfficeIcon class="h-5 w-5" />
                                    <span class="hidden md:block">
                                        {{ company.activeName || 'Todas las empresas' }}
                                    </span>
                                </button>
                            </template>

                            <!-- All Companies Option -->
                            <MenuItem v-slot="{ active }">
                                <button
                                    @click="switchCompany(null)"
                                    :class="[
                                        active ? 'bg-gray-100 dark:bg-slate-700' : '',
                                        'dropdown-item flex items-center justify-between'
                                    ]"
                                    type="button"
                                >
                                    <div class="flex items-center gap-2">
                                        <BuildingOfficeIcon class="h-5 w-5 text-gray-400" />
                                        <span>Todas las empresas</span>
                                    </div>
                                    <CheckIcon
                                        v-if="!company.activeId"
                                        class="h-4 w-4 text-primary-600 dark:text-primary-400"
                                    />
                                </button>
                            </MenuItem>

                            <!-- Divider -->
                            <div class="dropdown-divider" />

                            <!-- User Companies -->
                            <MenuItem v-for="comp in company.userCompanies" :key="comp.id" v-slot="{ active }">
                                <button
                                    @click="switchCompany(comp.id)"
                                    :class="[
                                        active ? 'bg-gray-100 dark:bg-slate-700' : '',
                                        'dropdown-item flex items-center justify-between'
                                    ]"
                                    type="button"
                                >
                                    <div class="flex items-center gap-2">
                                        <div
                                            class="w-2 h-2 rounded-full"
                                            :style="{ backgroundColor: comp.color || '#6366f1' }"
                                        />
                                        <span>{{ comp.nombre }}</span>
                                    </div>
                                    <CheckIcon
                                        v-if="company.activeId === comp.id"
                                        class="h-4 w-4 text-primary-600 dark:text-primary-400"
                                    />
                                </button>
                            </MenuItem>

                            <!-- Divider -->
                            <div class="dropdown-divider" />

                            <!-- Manage Companies -->
                            <MenuItem v-slot="{ active }">
                                <Link
                                    :href="route('companies.index')"
                                    :class="[
                                        active ? 'bg-gray-100 dark:bg-slate-700' : '',
                                        'dropdown-item flex items-center gap-2'
                                    ]"
                                >
                                    <Cog6ToothIcon class="h-5 w-5 text-gray-400" />
                                    <span>Gestionar Empresas</span>
                                </Link>
                            </MenuItem>
                        </Dropdown>

                        <!-- User Dropdown -->
                        <Dropdown align="right" width="48">
                            <template #trigger>
                                <button
                                    class="flex items-center gap-2 px-3 py-2 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-100 dark:text-slate-300 dark:hover:bg-slate-700 transition-colors"
                                    type="button"
                                >
                                    <div class="w-8 h-8 rounded-full bg-gradient-to-br from-primary-500 to-primary-600 flex items-center justify-center text-white font-semibold shadow-md">
                                        {{ userInitial }}
                                    </div>
                                    <span class="hidden lg:block">{{ auth.user?.name }}</span>
                                </button>
                            </template>

                            <!-- User Info Header -->
                            <div class="px-4 py-3 border-b border-gray-200 dark:border-slate-700">
                                <p class="text-sm font-medium text-gray-900 dark:text-white">
                                    {{ auth.user?.name }}
                                </p>
                                <p class="text-xs text-gray-500 dark:text-slate-400 truncate">
                                    {{ auth.user?.email }}
                                </p>
                            </div>

                            <!-- Profile Link -->
                            <MenuItem v-slot="{ active }">
                                <Link
                                    :href="route('profile.edit')"
                                    :class="[
                                        active ? 'bg-gray-100 dark:bg-slate-700' : '',
                                        'dropdown-item flex items-center gap-2'
                                    ]"
                                >
                                    <UserCircleIcon class="h-5 w-5 text-gray-400" />
                                    <span>Mi Perfil</span>
                                </Link>
                            </MenuItem>

                            <!-- Companies Link -->
                            <MenuItem v-slot="{ active }">
                                <Link
                                    :href="route('companies.index')"
                                    :class="[
                                        active ? 'bg-gray-100 dark:bg-slate-700' : '',
                                        'dropdown-item flex items-center gap-2'
                                    ]"
                                >
                                    <BuildingOfficeIcon class="h-5 w-5 text-gray-400" />
                                    <span>Empresas</span>
                                </Link>
                            </MenuItem>

                            <!-- Divider -->
                            <div class="dropdown-divider" />

                            <!-- Logout -->
                            <MenuItem v-slot="{ active }">
                                <button
                                    @click="logout"
                                    :class="[
                                        active ? 'bg-gray-100 dark:bg-slate-700' : '',
                                        'dropdown-item flex items-center gap-2 text-red-600 dark:text-red-400'
                                    ]"
                                    type="button"
                                >
                                    <ArrowRightOnRectangleIcon class="h-5 w-5" />
                                    <span>Cerrar Sesión</span>
                                </button>
                            </MenuItem>
                        </Dropdown>

                        <!-- Mobile menu button -->
                        <button
                            @click="showMobileMenu = !showMobileMenu"
                            class="sm:hidden p-2 rounded-lg text-gray-600 hover:text-gray-900 hover:bg-gray-100 dark:text-slate-400 dark:hover:text-slate-100 dark:hover:bg-slate-700 transition-colors"
                            type="button"
                        >
                            <Bars3Icon v-if="!showMobileMenu" class="h-6 w-6" />
                            <XMarkIcon v-else class="h-6 w-6" />
                        </button>
                    </div>
                </div>
            </div>

            <!-- Mobile menu -->
            <Transition
                enter-active-class="transition-all duration-300 ease-out"
                enter-from-class="opacity-0 -translate-y-2"
                enter-to-class="opacity-100 translate-y-0"
                leave-active-class="transition-all duration-200 ease-in"
                leave-from-class="opacity-100 translate-y-0"
                leave-to-class="opacity-0 -translate-y-2"
            >
                <div
                    v-show="showMobileMenu"
                    class="sm:hidden border-t border-gray-200/50 dark:border-slate-700/50 animate-slide-in"
                >
                    <div class="px-2 pt-2 pb-3 space-y-1">
                        <Link
                            :href="route('trips.index')"
                            :class="[
                                route().current('trips.*')
                                    ? 'bg-primary-100 text-primary-700 dark:bg-primary-900/30 dark:text-primary-400'
                                    : 'text-gray-700 hover:bg-gray-100 dark:text-slate-300 dark:hover:bg-slate-700',
                                'flex items-center gap-2 px-3 py-2 rounded-lg text-base font-medium transition-all duration-300'
                            ]"
                        >
                            <CurrencyDollarIcon class="h-5 w-5" />
                            Importaciones
                        </Link>

                        <Link
                            :href="route('analytics.index')"
                            :class="[
                                route().current('analytics.*')
                                    ? 'bg-primary-100 text-primary-700 dark:bg-primary-900/30 dark:text-primary-400'
                                    : 'text-gray-700 hover:bg-gray-100 dark:text-slate-300 dark:hover:bg-slate-700',
                                'flex items-center gap-2 px-3 py-2 rounded-lg text-base font-medium transition-all duration-300'
                            ]"
                        >
                            <ChartBarIcon class="h-5 w-5" />
                            Analítica
                        </Link>
                    </div>
                </div>
            </Transition>
        </nav>

        <!-- Page Content -->
        <main class="py-8">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <slot />
            </div>
        </main>

        <!-- Toast Notifications -->
        <Toast />
    </div>
</template>
