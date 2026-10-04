<script setup lang="ts">
import { computed, ref, watch, nextTick, onMounted, onUnmounted } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import { useRoute } from '@/Composables/useRoute';
import { inputValue } from '@/Utils/input';
import { useDarkMode } from '@/Composables/useDarkMode';
import { Squares2X2Icon, ChartBarIcon, BuildingOffice2Icon, TagIcon, SunIcon, MoonIcon, ArrowRightOnRectangleIcon, Bars3Icon, XMarkIcon } from '@heroicons/vue/24/outline';
const props = withDefaults(defineProps<{ errorHeading?: string }>(), { errorHeading: 'No se guardaron los cambios.' });
const route = useRoute();
const page = usePage();
const { isDark, toggleTheme } = useDarkMode();
const menuOpen = ref(false);
const navRef = ref<HTMLElement | null>(null);
const menuButton = ref<HTMLButtonElement | null>(null);
let mobileQuery: MediaQueryList | undefined;
const closeOnDesktop = (event: MediaQueryListEvent) => { if (!event.matches) menuOpen.value = false; };
onMounted(() => { mobileQuery = window.matchMedia('(max-width: 760px)'); mobileQuery.addEventListener('change', closeOnDesktop); });
onUnmounted(() => mobileQuery?.removeEventListener('change', closeOnDesktop));
watch(menuOpen, async open => { await nextTick(); if (open) navRef.value?.querySelector('a')?.focus(); else menuButton.value?.focus(); });
const trapNavFocus = (event: KeyboardEvent) => {
    if (!menuOpen.value || !navRef.value || event.key !== 'Tab') return;
    const nodes = Array.from(navRef.value.querySelectorAll<HTMLElement>('a,button')).filter(node => node.getClientRects().length);
    const first = nodes[0], last = nodes[nodes.length - 1];
    if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus(); }
    else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus(); }
};
const comfortable = ref(false);
try { comfortable.value = localStorage.getItem('costoreal-density') === 'comfortable'; } catch {}
watch(comfortable, value => { try { localStorage.setItem('costoreal-density', value ? 'comfortable' : 'compact'); } catch {} });
const nav = [
    { label: 'Importaciones', route: 'trips.index', pattern: 'trips.*', icon: Squares2X2Icon },
    { label: 'Analítica', route: 'analytics.index', pattern: 'analytics.*', icon: ChartBarIcon },
    { label: 'Empresas', route: 'companies.index', pattern: 'companies.*', icon: BuildingOffice2Icon },
    { label: 'Categorías', route: 'categories.index', pattern: 'categories.*', icon: TagIcon },
];
const company = computed(() => page.props.company);
const switchCompany = (event: Event) => router.post(route('company.switch', inputValue(event) || 'all'), {}, { preserveState: false });
const errors = computed(() => Object.values(page.props.errors || {}).flatMap((value: unknown) => typeof value === 'string' ? [value] : value && typeof value === 'object' ? Object.values(value).filter((message): message is string => typeof message === 'string') : []));
const flash = computed(() => page.props.flash || {});
</script>
<template>
    <div class="financial-shell" :class="{ 'density-comfortable': comfortable }">
        <a class="f-skip" href="#main-content">Saltar al contenido</a>
        <aside ref="navRef" class="f-sidebar" :class="{ 'is-open': menuOpen }" :role="menuOpen ? 'dialog' : undefined" :aria-modal="menuOpen ? true : undefined" aria-label="Navegación" @keydown="trapNavFocus" @keydown.esc="menuOpen = false">
            <Link :href="route('trips.index')" class="f-brand" aria-label="Costo Real, control de importaciones"><span class="f-brand-mark">c<span>r</span></span><span>Costo Real<small>Control financiero</small></span></Link>
            <button class="f-icon-button f-mobile-only" @click="menuOpen = false" aria-label="Cerrar navegación"><XMarkIcon /></button>
            <p class="f-nav-label">ESPACIO DE TRABAJO</p>
            <nav aria-label="Navegación principal"><Link v-for="item in nav" :key="item.route" :href="route(item.route)" :aria-current="route().current(item.pattern) ? 'page' : undefined" :class="{ active: route().current(item.pattern) }"><component :is="item.icon" />{{ item.label }}</Link></nav>
            <div class="f-sidebar-bottom"><span class="f-status-dot"></span> Uso personal<small>Costos expresados en CLP</small></div>
        </aside>
        <button v-if="menuOpen" class="f-nav-backdrop" @click="menuOpen = false" aria-label="Cerrar navegación"></button>
        <div class="f-workspace" :inert="menuOpen ? true : undefined">
            <header class="f-topbar">
                <button ref="menuButton" class="f-icon-button f-mobile-only" @click="menuOpen = !menuOpen" aria-label="Abrir navegación" :aria-expanded="menuOpen"><Bars3Icon /></button>
                <div class="f-company-control"><BuildingOffice2Icon /><label class="sr-only" for="active-company">Empresa activa</label><select id="active-company" :value="company?.activeId || ''" @change="switchCompany"><option value="">Todas mis empresas</option><option v-for="item in company?.userCompanies" :key="item.id" :value="item.id">{{ item.nombre }}</option></select></div>
                <div class="f-topbar-actions"><button class="f-icon-button f-density-toggle" @click="comfortable = !comfortable" :aria-pressed="comfortable" title="Cambiar densidad">{{ comfortable ? 'Cómoda' : 'Compacta' }}</button><button class="f-icon-button" @click="toggleTheme" :aria-label="isDark ? 'Activar tema claro' : 'Activar tema oscuro'"><SunIcon v-if="isDark" /><MoonIcon v-else /></button><Link class="f-avatar" :href="route('profile.edit')" :aria-label="`Perfil de ${page.props.auth?.user?.name}`">{{ page.props.auth?.user?.name?.slice(0,1) }}</Link><button class="f-icon-button f-logout" @click="router.post(route('logout'))" aria-label="Cerrar sesión"><ArrowRightOnRectangleIcon /></button></div>
            </header>
            <main id="main-content" class="f-main" tabindex="-1">
                <div v-if="flash.success" class="f-notice f-notice-success" role="status">{{ flash.success }}</div>
                <div v-if="flash.error" class="f-notice f-notice-error" role="alert">{{ flash.error }}</div>
                <div v-if="flash.info" class="f-notice" role="status">{{ flash.info }}</div>
                <div v-if="errors.length" class="f-notice f-notice-error" role="alert"><strong>{{ props.errorHeading }}</strong><p v-for="(error, index) in errors" :key="index">{{ error }}</p></div>
                <slot name="header" /><slot />
            </main>
        </div>
    </div>
</template>
