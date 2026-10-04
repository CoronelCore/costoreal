<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import type { Pagination } from '@/Types/financial';
defineProps<{ page: Pagination<unknown>; label: string }>();
</script>
<template>
    <div v-if="page.total" class="f-pagination"><span>{{ page.from }}–{{ page.to }} de {{ page.total }} {{ label }}</span><nav class="f-pagination-links" :aria-label="`Páginas de ${label}`"><Link v-if="page.prev_page_url" :href="page.prev_page_url" class="f-button" preserve-scroll>Anterior</Link><span class="f-hint" style="padding:8px">{{ page.current_page }} / {{ page.last_page }}</span><Link v-if="page.next_page_url" :href="page.next_page_url" class="f-button" preserve-scroll>Siguiente</Link></nav></div>
</template>
