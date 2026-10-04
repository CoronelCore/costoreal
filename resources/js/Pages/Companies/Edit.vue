<script setup lang="ts">
import RecordEditor from "@/Components/Catalogs/RecordEditor.vue";
import type { CatalogRecord } from "@/Types/catalog";
defineProps<{ companyRecord: CatalogRecord }>();
</script>
<template><RecordEditor kind="company" :record="companyRecord" /></template>
