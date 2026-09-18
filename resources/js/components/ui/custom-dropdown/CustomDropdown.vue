<script setup>
import { computed, nextTick, onMounted, onUnmounted, ref, useAttrs } from 'vue';
import { cn } from '../../../lib/utils';
import { Icon } from '../icon';

defineOptions({
    inheritAttrs: false,
});

const props = defineProps({
    modelValue: {
        type: [String, Number],
        default: '',
    },
    items: {
        type: Array,
        default: () => [],
    },
    placeholder: {
        type: String,
        default: '',
    },
    invalid: {
        type: Boolean,
        default: false,
    },
    disabled: {
        type: Boolean,
        default: false,
    },
    class: {
        type: [String, Object, Array],
        default: '',
    },
});

const emit = defineEmits(['update:modelValue']);

const attrs = useAttrs();
const root = ref(null);
const optionRefs = ref([]);
const open = ref(false);
const selectedItem = computed(() =>
    props.items.find((item) => item.value === props.modelValue),
);
const selectedIndex = computed(() =>
    props.items.findIndex((item) => item.value === props.modelValue),
);
const triggerClasses = computed(() =>
    cn(
        'flex h-10 w-full items-center justify-between rounded-lg border border-line bg-ground px-3 text-left text-sm text-charcoal outline-none transition-[box-shadow,border-color] focus:border-primary focus:ring-[3px] focus:ring-primary/35 disabled:cursor-not-allowed disabled:opacity-45',
        props.invalid &&
            'border-danger bg-danger/5 focus:border-danger focus:ring-danger/35',
        props.class,
    ),
);

const focusOption = (index) => {
    nextTick(() => optionRefs.value[index]?.focus());
};

const close = () => {
    open.value = false;
};

const toggle = () => {
    if (props.disabled) {
        return;
    }

    open.value = !open.value;

    if (open.value) {
        focusOption(Math.max(selectedIndex.value, 0));
    }
};

const select = (item) => {
    emit('update:modelValue', item.value);
    close();
};

const moveFocus = (currentIndex, direction) => {
    const nextIndex = Math.min(
        Math.max(currentIndex + direction, 0),
        props.items.length - 1,
    );

    focusOption(nextIndex);
};

const onTriggerKeydown = (event) => {
    if (['ArrowDown', 'ArrowUp', 'Enter', ' '].includes(event.key)) {
        event.preventDefault();
        toggle();
    }
};

const onOptionKeydown = (event, item, index) => {
    if (event.key === 'ArrowDown') {
        event.preventDefault();
        moveFocus(index, 1);
    }

    if (event.key === 'ArrowUp') {
        event.preventDefault();
        moveFocus(index, -1);
    }

    if (event.key === 'Escape') {
        event.preventDefault();
        close();
        root.value?.querySelector('button')?.focus();
    }

    if (['Enter', ' '].includes(event.key)) {
        event.preventDefault();
        select(item);
    }
};

const onDocumentPointerDown = (event) => {
    if (root.value && !root.value.contains(event.target)) {
        close();
    }
};

onMounted(() => {
    document.addEventListener('pointerdown', onDocumentPointerDown);
});

onUnmounted(() => {
    document.removeEventListener('pointerdown', onDocumentPointerDown);
});
</script>

<template>
    <div
        ref="root"
        class="relative w-full"
    >
        <button
            type="button"
            :class="triggerClasses"
            :disabled="disabled"
            :aria-expanded="open"
            :aria-invalid="invalid ? 'true' : undefined"
            aria-haspopup="listbox"
            v-bind="attrs"
            @click="toggle"
            @keydown="onTriggerKeydown"
        >
            <span :class="!selectedItem && 'text-muted'">
                {{ selectedItem?.title || placeholder }}
            </span>
            <Icon
                :name="['fas', 'chevron-down']"
                size="sm"
                :class="open ? 'rotate-180' : ''"
            />
        </button>
        <div
            v-if="open"
            class="absolute z-20 mt-1 w-full overflow-hidden rounded-lg border border-line bg-ground py-1 shadow-toast"
            role="listbox"
        >
            <button
                v-for="(item, index) in items"
                :key="item.value"
                ref="optionRefs"
                type="button"
                class="flex w-full flex-col px-3 py-2 text-left outline-none hover:bg-page focus:bg-page"
                :class="item.value === modelValue && 'bg-primary/10'"
                :aria-selected="item.value === modelValue"
                role="option"
                @click="select(item)"
                @keydown="onOptionKeydown($event, item, index)"
            >
                <span class="text-sm font-medium text-charcoal">
                    {{ item.title }}
                </span>
                <span
                    v-if="item.description"
                    class="mt-0.5 text-xs text-muted"
                >
                    {{ item.description }}
                </span>
            </button>
        </div>
    </div>
</template>
