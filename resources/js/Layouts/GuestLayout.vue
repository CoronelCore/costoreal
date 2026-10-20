<script setup lang="ts">
import { Link } from "@inertiajs/vue3";
import { useDarkMode } from "@/Composables/useDarkMode";
import BrandMark from "@/Components/BrandMark.vue";
import { SunIcon, MoonIcon } from "@heroicons/vue/24/outline";
const { isDark, toggleTheme } = useDarkMode();
</script>
<template>
    <div class="financial-shell f-auth-shell">
        <a class="f-skip" href="#main-content">Saltar al contenido</a>
        <header class="f-auth-header">
            <Link href="/" class="f-brand" aria-label="CostoReal"
                ><BrandMark /><span>CostoReal<small>Control financiero</small></span></Link
            ><button
                class="f-icon-button"
                @click="toggleTheme"
                :aria-label="
                    isDark ? 'Activar tema claro' : 'Activar tema oscuro'
                "
            >
                <SunIcon v-if="isDark" /><MoonIcon v-else />
            </button>
        </header>
        <div class="f-auth-content">
            <section class="f-auth-intro" aria-label="Control de importaciones">
                <p class="f-eyebrow">CADA PESO, EN SU LUGAR</p>
                <h2>Del precio de compra<br />al costo real.</h2>
                <p>
                    Organiza tus importaciones, distribuye los gastos y revisa
                    tus decisiones con cifras que puedes explicar.
                </p>
                <ol>
                    <li><span>01</span> Compra y conversión</li>
                    <li><span>02</span> Gastos y reparto</li>
                    <li><span>03</span> Costo, revisión y reportes</li>
                </ol>
            </section>
            <main id="main-content" class="f-auth-panel f-panel" tabindex="-1">
                <slot />
            </main>
        </div>
        <footer class="f-auth-footer">
            © {{ new Date().getFullYear() }} CostoReal · Control personal de
            importaciones
        </footer>
    </div>
</template>
