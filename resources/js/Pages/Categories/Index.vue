<script setup lang="ts">
import RecordList from "@/Components/Catalogs/RecordList.vue";
import type { CatalogRecord } from "@/Types/catalog";
defineProps<{ categories: CatalogRecord[] }>();
</script>
<template><RecordList kind="category" :records="categories" /></template>
