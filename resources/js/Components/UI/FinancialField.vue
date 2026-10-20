<script setup lang="ts">
import { ref } from "vue";
import { EyeIcon, EyeSlashIcon } from "@heroicons/vue/24/outline";
import { inputValue } from "@/Utils/input";
defineOptions({ inheritAttrs: false });
const props = withDefaults(
    defineProps<{
        id: string;
        label: string;
        modelValue: string | number;
        type?: string;
        error?: string;
        help?: string;
        multiline?: boolean;
    }>(),
    { type: "text" },
);
const emit = defineEmits<{ "update:modelValue": [value: string] }>();
const visible = ref(false);
</script>
<template>
    <div class="f-field">
        <label :for="id">{{ label }}</label>
        <div class="f-control-wrap">
            <textarea
                v-if="multiline"
                :id="id"
                :value="modelValue"
                v-bind="$attrs"
                class="f-input"
                :aria-invalid="!!error"
                :aria-describedby="
                    [help && `${id}-help`, error && `${id}-error`]
                        .filter(Boolean)
                        .join(' ') || undefined
                "
                @input="emit('update:modelValue', inputValue($event))"
            ></textarea>
            <input
                v-else
                :id="id"
                :type="type === 'password' && visible ? 'text' : type"
                :value="modelValue"
                v-bind="$attrs"
                class="f-input"
                :class="{ 'f-password-input': type === 'password' }"
                :aria-invalid="!!error"
                :aria-describedby="
                    [help && `${id}-help`, error && `${id}-error`]
                        .filter(Boolean)
                        .join(' ') || undefined
                "
                @input="emit('update:modelValue', inputValue($event))"
            />
            <button
                v-if="type === 'password'"
                type="button"
                class="f-password-toggle"
                :aria-label="`${visible ? 'Ocultar' : 'Mostrar'} ${label.toLowerCase()}`"
                :aria-pressed="visible"
                :disabled="!!$attrs.disabled"
                @click="visible = !visible"
            >
                <EyeSlashIcon v-if="visible" /><EyeIcon v-else />
            </button>
        </div>
        <p :id="`${id}-help`" class="f-hint" v-if="help">{{ help }}</p>
        <slot />
        <p :id="`${id}-error`" class="f-field-error" role="alert" v-if="error">
            {{ error }}
        </p>
    </div>
</template>
