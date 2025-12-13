<script setup>
import { computed } from 'vue';

const props = defineProps({
    noPadding: {
        type: Boolean,
        default: false
    },
    hover: {
        type: Boolean,
        default: false
    }
});

const cardClasses = computed(() => {
    const classes = ['card'];

    if (props.hover) {
        classes.push('card-hover');
    }

    return classes.join(' ');
});
</script>

<template>
    <div :class="cardClasses">
        <div v-if="!noPadding" class="card-body">
            <slot />
        </div>
        <slot v-else />
    </div>
</template>
