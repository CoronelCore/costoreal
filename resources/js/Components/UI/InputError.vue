<script setup>
defineProps({
    message: {
        type: String,
        default: ''
    }
});
</script>

<template>
    <Transition
        enter-active-class="transition ease-out duration-200"
        enter-from-class="opacity-0 transform -translate-y-1"
        enter-to-class="opacity-100 transform translate-y-0"
        leave-active-class="transition ease-in duration-150"
        leave-from-class="opacity-100 transform translate-y-0"
        leave-to-class="opacity-0 transform -translate-y-1"
    >
        <p v-if="message" class="text-sm text-danger-600 dark:text-danger-400 mt-1.5 font-medium">
            {{ message }}
        </p>
    </Transition>
</template>
