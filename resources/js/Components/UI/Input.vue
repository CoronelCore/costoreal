<script setup>
import { computed } from 'vue';

const props = defineProps({
    modelValue: {
        type: [String, Number],
        default: ''
    },
    type: {
        type: String,
        default: 'text'
    },
    placeholder: {
        type: String,
        default: ''
    },
    disabled: {
        type: Boolean,
        default: false
    },
    error: {
        type: String,
        default: ''
    },
    id: {
        type: String,
        default: undefined
    },
    name: {
        type: String,
        default: undefined
    },
    required: {
        type: Boolean,
        default: false
    },
    autocomplete: {
        type: String,
        default: undefined
    }
});

const emit = defineEmits(['update:modelValue']);

const inputClasses = computed(() => {
    const classes = ['input'];

    if (props.error) {
        classes.push('input-error');
    }

    return classes.join(' ');
});

const updateValue = (event) => {
    emit('update:modelValue', event.target.value);
};
</script>

<template>
    <input
        :id="id"
        :type="type"
        :name="name"
        :value="modelValue"
        :placeholder="placeholder"
        :disabled="disabled"
        :required="required"
        :autocomplete="autocomplete"
        :class="inputClasses"
        @input="updateValue"
    >
</template>
