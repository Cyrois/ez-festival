<script setup>
import { labelTokens } from '../../../lib/labelTokens';

defineProps({
    modelValue: { type: String, required: true },
    colors: { type: Array, required: true },
    disabled: { type: Boolean, default: false },
    invalid: { type: Boolean, default: false },
});
const emit = defineEmits(['update:modelValue']);
</script>

<template>
    <div
        class="flex flex-wrap items-center gap-2"
        role="group"
        :aria-invalid="invalid || undefined"
    >
        <button
            v-for="color in colors"
            :key="color"
            type="button"
            class="h-6 w-6 cursor-pointer rounded-full border-2 border-ground ring-1 ring-line focus-visible:ring-[3px] focus-visible:ring-primary/35 focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-45"
            :class="[
                labelTokens[color]?.swatch,
                modelValue === color && 'ring-2 ring-primary ring-offset-1',
            ]"
            :aria-label="$t(`labels.colors.${color}`)"
            :title="$t(`labels.colors.${color}`)"
            :aria-pressed="modelValue === color"
            :disabled="disabled"
            @click="emit('update:modelValue', color)"
        />
    </div>
</template>
