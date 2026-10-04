<script setup>
import { computed, useAttrs } from 'vue';
import { IconButton } from '../icon-button';
import { Input } from '../input';

defineOptions({ inheritAttrs: false });
const props = defineProps({
    modelValue: { type: [String, Number], default: 1 },
    min: { type: Number, default: 1 },
    max: { type: Number, default: 2147483647 },
    disabled: { type: Boolean, default: false },
    invalid: { type: Boolean, default: false },
    label: { type: String, required: true },
    removeAtOne: { type: Boolean, default: false },
    removeDisabled: { type: Boolean, default: false },
    removeLabel: { type: String, default: '' },
    disabledReason: { type: String, default: '' },
});
const emit = defineEmits(['update:modelValue', 'remove']);
const attrs = useAttrs();
const amount = computed(() => Number(props.modelValue) || props.min);
const isRemove = computed(() => props.removeAtOne && amount.value === 1);
const decreaseDisabled = computed(
    () =>
        props.disabled ||
        (isRemove.value ? props.removeDisabled : amount.value <= props.min),
);
const clamp = (value) => Math.max(props.min, Math.min(props.max, value));
const adjust = (delta) => {
    if (!props.disabled)
        emit('update:modelValue', clamp(Math.trunc(amount.value) + delta));
};
const decrease = () => {
    if (decreaseDisabled.value) return;
    if (isRemove.value) emit('remove');
    else adjust(-1);
};
const clampInput = (event) => {
    if (
        event.target.value !== '' &&
        Number.isFinite(Number(event.target.value))
    ) {
        event.target.value = clamp(Number(event.target.value));
    }
};
</script>

<template>
    <div class="flex items-center gap-1">
        <span
            :title="
                decreaseDisabled && disabledReason ? disabledReason : undefined
            "
            :aria-label="
                decreaseDisabled && disabledReason ? disabledReason : undefined
            "
            :tabindex="decreaseDisabled && disabledReason ? 0 : undefined"
        >
            <IconButton
                :icon="['fas', isRemove ? 'circle-minus' : 'minus']"
                :tone="isRemove ? 'delete' : 'default'"
                class="h-10 w-10 shrink-0"
                :label="
                    isRemove
                        ? removeLabel
                        : $t('ui.quantity.decrease', { label })
                "
                :disabled="decreaseDisabled"
                @click="decrease"
            />
        </span>
        <Input
            v-bind="attrs"
            :model-value="modelValue"
            :aria-label="label"
            type="number"
            :min="min"
            :max="max"
            step="1"
            class="w-16 [appearance:textfield] px-2 text-center [&::-webkit-inner-spin-button]:appearance-none [&::-webkit-outer-spin-button]:appearance-none"
            :invalid="invalid"
            :disabled="disabled"
            @input.capture="clampInput"
            @update:model-value="emit('update:modelValue', $event)"
        />
        <IconButton
            :icon="['fas', 'plus']"
            class="h-10 w-10 shrink-0"
            :label="$t('ui.quantity.increase', { label })"
            :disabled="disabled || amount >= max"
            @click="adjust(1)"
        />
    </div>
</template>
