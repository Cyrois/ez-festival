<script setup>
import { computed, useAttrs, useId } from 'vue';
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
    size: {
        type: String,
        default: 'md',
        validator: (value) => ['sm', 'md'].includes(value),
    },
    removeAtOne: { type: Boolean, default: false },
    removeDisabled: { type: Boolean, default: false },
    removeLabel: { type: String, default: '' },
    disabledReason: { type: String, default: '' },
});
const emit = defineEmits(['update:modelValue', 'remove']);
const attrs = useAttrs();
const tooltipId = useId();
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
            class="group relative inline-flex shrink-0"
            :aria-label="
                decreaseDisabled && disabledReason ? disabledReason : undefined
            "
            :aria-describedby="
                decreaseDisabled && disabledReason ? tooltipId : undefined
            "
            :tabindex="decreaseDisabled && disabledReason ? 0 : undefined"
        >
            <IconButton
                :icon="['fas', isRemove ? 'circle-minus' : 'minus']"
                :tone="isRemove ? 'delete' : 'default'"
                class="shrink-0 bg-ground disabled:pointer-events-none"
                :class="size === 'md' ? 'h-10 w-10' : ''"
                :label="
                    isRemove
                        ? removeLabel
                        : $t('ui.quantity.decrease', { label })
                "
                :disabled="decreaseDisabled"
                @click="decrease"
            />
            <span
                v-if="decreaseDisabled && disabledReason"
                :id="tooltipId"
                role="tooltip"
                class="pointer-events-none absolute bottom-full left-0 z-50 mb-2 hidden w-56 rounded-lg bg-charcoal px-3 py-2 text-xs text-white shadow-lg group-hover:block group-focus-visible:block sm:right-0 sm:left-auto"
            >
                {{ disabledReason }}
            </span>
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
            :class="size === 'sm' ? 'h-8' : ''"
            :invalid="invalid"
            :disabled="disabled"
            @input.capture="clampInput"
            @update:model-value="emit('update:modelValue', $event)"
        />
        <IconButton
            :icon="['fas', 'plus']"
            class="shrink-0 bg-ground"
            :class="size === 'md' ? 'h-10 w-10' : ''"
            :label="$t('ui.quantity.increase', { label })"
            :disabled="disabled || amount >= max"
            @click="adjust(1)"
        />
    </div>
</template>
