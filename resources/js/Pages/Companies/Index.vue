<script setup lang="ts">
import RecordList from "@/Components/Catalogs/RecordList.vue";
import type { CatalogRecord } from "@/Types/catalog";
defineProps<{ companies: CatalogRecord[] }>();
</script>
<template><RecordList kind="company" :records="companies" /></template>
