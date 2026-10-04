<script setup lang="ts">
import RecordEditor from "@/Components/Catalogs/RecordEditor.vue";
import type { CatalogRecord } from "@/Types/catalog";
defineProps<{ category: CatalogRecord }>();
</script>
<template><RecordEditor kind="category" :record="category" /></template>
