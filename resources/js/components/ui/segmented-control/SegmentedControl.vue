<script setup>
import { computed, nextTick, ref } from 'vue';
import { Icon } from '../icon';
import { cn } from '../../../lib/utils';

const props = defineProps({
    modelValue: {
        type: [String, Number],
        default: '',
    },
    options: {
        type: Array,
        default: () => [],
    },
    disabled: {
        type: Boolean,
        default: false,
    },
    ariaLabel: {
        type: String,
        default: '',
    },
    class: {
        type: [String, Object, Array],
        default: '',
    },
    equal: {
        type: Boolean,
        default: false,
    },
    unselectedClass: {
        type: String,
        default: 'bg-transparent text-muted hover:text-charcoal',
    },
});

const emit = defineEmits(['update:modelValue']);

const rootEl = ref(null);

const groupLabel = computed(() => props.ariaLabel || undefined);

const trackClass = computed(() =>
    cn(
        'inline-flex rounded-lg bg-page p-1',
        props.disabled && 'pointer-events-none opacity-45',
        props.class,
    ),
);

const selectedOptionClass = (option) => {
    if (option.selectedClass) {
        return option.selectedClass;
    }

    if (option.variant === 'danger') {
        return 'bg-danger text-white shadow-sm';
    }

    if (option.variant === 'primary') {
        return 'bg-primary text-white shadow-sm';
    }

    return 'bg-ground text-primary ring-1 ring-line';
};

const optionClass = (option) =>
    cn(
        'inline-flex cursor-pointer items-center justify-center gap-2 rounded-md px-3 py-1.5 text-sm font-bold transition-colors',
        props.equal && 'flex-1',
        'focus-visible:outline-none focus-visible:ring-[3px] focus-visible:ring-primary/35',
        props.modelValue === option.value
            ? selectedOptionClass(option)
            : props.unselectedClass,
    );

const onSelect = (value) => {
    if (props.disabled) {
        return;
    }

    emit('update:modelValue', value);
};

const focusChecked = async () => {
    await nextTick();
    rootEl.value?.querySelector('[role="radio"][aria-checked="true"]')?.focus();
};

const moveSelection = (delta) => {
    if (props.disabled || !props.options.length) {
        return;
    }

    const currentIndex = props.options.findIndex(
        (option) => option.value === props.modelValue,
    );
    const start = currentIndex < 0 ? 0 : currentIndex;
    const nextIndex =
        (start + delta + props.options.length) % props.options.length;

    emit('update:modelValue', props.options[nextIndex].value);
    focusChecked();
};

const onKeydown = (event) => {
    if (props.disabled) {
        return;
    }

    if (event.key === 'ArrowRight' || event.key === 'ArrowDown') {
        event.preventDefault();
        moveSelection(1);
        return;
    }

    if (event.key === 'ArrowLeft' || event.key === 'ArrowUp') {
        event.preventDefault();
        moveSelection(-1);
    }
};
</script>

<template>
    <div
        ref="rootEl"
        role="radiogroup"
        :class="trackClass"
        :aria-label="groupLabel"
        :aria-disabled="disabled ? 'true' : undefined"
        @keydown="onKeydown"
    >
        <button
            v-for="option in options"
            :key="String(option.value)"
            type="button"
            role="radio"
            :aria-checked="modelValue === option.value ? 'true' : 'false'"
            :tabindex="modelValue === option.value ? 0 : -1"
            :disabled="disabled"
            :class="optionClass(option)"
            @click="onSelect(option.value)"
        >
            <Icon
                v-if="option.icon"
                :name="option.icon"
                size="sm"
            />
            {{ option.label }}
        </button>
    </div>
</template>
