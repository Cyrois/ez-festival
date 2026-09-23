<script setup>
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from 'vue';
import { trans } from 'laravel-vue-i18n';
import { labelTokens } from '../../../lib/labelTokens';
import { cn } from '../../../lib/utils';
import { Button } from '../button';
import { Icon } from '../icon';
import { Tag } from '../tag';
import {
    canCreateLabel,
    normalizeLabelName,
    resolveLabelEnterAction,
} from './labelCombobox';

const props = defineProps({
    modelValue: {
        type: Array,
        default: () => [],
    },
    newLabels: {
        type: Array,
        default: () => [],
    },
    labels: {
        type: Array,
        default: () => [],
    },
    allowCreate: {
        type: Boolean,
        default: false,
    },
    colors: {
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
    id: {
        type: String,
        default: undefined,
    },
    class: {
        type: [String, Object, Array],
        default: '',
    },
});

const emit = defineEmits(['update:modelValue', 'update:newLabels']);

const root = ref(null);
const input = ref(null);
const open = ref(false);
const query = ref('');
const selectedColor = ref('');

const selectedIds = computed(() => new Set(props.modelValue.map(Number)));
const selectedLabels = computed(() =>
    props.labels.filter((label) => selectedIds.value.has(Number(label.id))),
);
const filteredLabels = computed(() => {
    const value = normalizeLabelName(query.value);

    if (!value) {
        return props.labels;
    }

    return props.labels.filter((label) =>
        normalizeLabelName(label.name).includes(value),
    );
});
const canCreateQuery = computed(() =>
    canCreateLabel({
        allowCreate: props.allowCreate,
        colors: props.colors,
        labels: props.labels,
        newLabels: props.newLabels,
        query: query.value,
    }),
);
const fieldClasses = computed(() =>
    cn(
        'flex min-h-10 w-full flex-wrap items-center gap-1.5 rounded-lg border border-line bg-ground px-2 py-1.5 text-sm text-charcoal outline-none transition-[box-shadow,border-color] focus-within:border-primary focus-within:ring-[3px] focus-within:ring-primary/35',
        props.invalid &&
            'border-danger bg-danger/5 focus-within:border-danger focus-within:ring-danger/35',
        props.disabled && 'cursor-not-allowed opacity-60',
        props.class,
    ),
);
const focusInput = () => {
    if (props.disabled) {
        return;
    }

    open.value = true;
    nextTick(() => input.value?.focus());
};

const toggleExisting = (label) => {
    const id = Number(label.id);
    const next = selectedIds.value.has(id)
        ? props.modelValue.filter((value) => Number(value) !== id)
        : [...props.modelValue, id];

    emit('update:modelValue', next);
    query.value = '';
    focusInput();
};

const removeExisting = (id) => {
    emit(
        'update:modelValue',
        props.modelValue.filter((value) => Number(value) !== Number(id)),
    );
};

const removeNew = (index) => {
    emit(
        'update:newLabels',
        props.newLabels.filter((_, labelIndex) => labelIndex !== index),
    );
};

const createLabel = () => {
    if (!canCreateQuery.value) {
        return;
    }

    emit('update:newLabels', [
        ...props.newLabels,
        {
            name: query.value.trim(),
            color: selectedColor.value,
        },
    ]);
    query.value = '';
    focusInput();
};

const onEnter = () => {
    const action = resolveLabelEnterAction({
        allowCreate: props.allowCreate,
        colors: props.colors,
        labels: props.labels,
        newLabels: props.newLabels,
        query: query.value,
        selectedIds: selectedIds.value,
    });

    if (action.type === 'toggle-existing') {
        toggleExisting(action.label);
    } else if (action.type === 'clear') {
        query.value = '';
    } else if (action.type === 'create') {
        createLabel();
    }
};

const onBackspace = () => {
    if (query.value || props.disabled) {
        return;
    }

    if (props.newLabels.length) {
        removeNew(props.newLabels.length - 1);
        return;
    }

    const last = selectedLabels.value.at(-1);
    if (last) {
        removeExisting(last.id);
    }
};

const onDocumentPointerDown = (event) => {
    if (root.value && !root.value.contains(event.target)) {
        open.value = false;
        query.value = '';
    }
};

watch(
    () => props.colors,
    (colors) => {
        if (!colors.includes(selectedColor.value)) {
            selectedColor.value = colors[0] ?? '';
        }
    },
    { immediate: true },
);

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
        <div
            :class="fieldClasses"
            @click="focusInput"
        >
            <Tag
                v-for="label in selectedLabels"
                :key="`existing-${label.id}`"
                :name="label.name"
                :color="label.color"
                :removable="!disabled"
                :remove-label="trans('labels.remove', { name: label.name })"
                @remove="removeExisting(label.id)"
            />
            <Tag
                v-for="(label, index) in newLabels"
                :key="`new-${normalizeLabelName(label.name)}-${index}`"
                :name="label.name"
                :color="label.color"
                :removable="!disabled"
                :remove-label="trans('labels.remove', { name: label.name })"
                @remove="removeNew(index)"
            />
            <input
                :id="id"
                ref="input"
                v-model="query"
                type="text"
                class="h-7 min-w-28 flex-1 border-0 bg-transparent px-1 text-sm outline-none placeholder:text-muted disabled:cursor-not-allowed"
                :placeholder="
                    selectedLabels.length || newLabels.length
                        ? trans('labels.search_placeholder')
                        : placeholder || trans('labels.placeholder')
                "
                :disabled="disabled"
                maxlength="255"
                :aria-expanded="open"
                :aria-invalid="invalid ? 'true' : undefined"
                aria-autocomplete="list"
                role="combobox"
                @focus="open = true"
                @keydown.enter.prevent="onEnter"
                @keydown.backspace="onBackspace"
                @keydown.esc="open = false"
            />
            <Icon
                v-if="!disabled"
                :name="['fas', 'chevron-down']"
                size="sm"
                class="mr-1 text-muted"
                :class="open ? 'rotate-180' : ''"
                aria-hidden="true"
            />
        </div>

        <div
            v-if="open && !disabled"
            class="absolute z-30 mt-1 w-full min-w-72 overflow-hidden rounded-lg border border-line bg-ground shadow-toast"
        >
            <div
                v-if="filteredLabels.length"
                class="max-h-56 overflow-y-auto py-1"
                role="listbox"
                aria-multiselectable="true"
            >
                <button
                    v-for="label in filteredLabels"
                    :key="label.id"
                    type="button"
                    class="flex w-full items-center justify-between gap-3 px-3 py-2 text-left hover:bg-page focus:bg-page focus:outline-none"
                    :aria-selected="selectedIds.has(Number(label.id))"
                    role="option"
                    @click="toggleExisting(label)"
                >
                    <Tag
                        :name="label.name"
                        :color="label.color"
                    />
                    <Icon
                        v-if="selectedIds.has(Number(label.id))"
                        :name="['fas', 'check']"
                        class="text-primary"
                        size="sm"
                    />
                </button>
            </div>
            <p
                v-else-if="!canCreateQuery"
                class="m-0 px-3 py-3 text-sm text-muted"
            >
                {{ trans('labels.empty') }}
            </p>

            <div
                v-if="canCreateQuery"
                class="border-t border-line p-3"
            >
                <p class="m-0 text-xs font-semibold text-charcoal">
                    {{ trans('labels.create_name', { name: query.trim() }) }}
                </p>
                <div class="mt-2 flex flex-wrap items-center gap-2">
                    <button
                        v-for="color in colors"
                        :key="color"
                        type="button"
                        class="h-6 w-6 rounded-full border-2 border-ground ring-1 ring-line focus-visible:ring-[3px] focus-visible:ring-primary/35 focus-visible:outline-none"
                        :class="[
                            labelTokens[color]?.swatch,
                            selectedColor === color &&
                                'ring-2 ring-primary ring-offset-1',
                        ]"
                        :aria-label="trans(`labels.colors.${color}`)"
                        :aria-pressed="selectedColor === color"
                        @click="selectedColor = color"
                    />
                </div>
                <div class="mt-3 flex justify-end gap-2">
                    <Button
                        type="button"
                        variant="ghost"
                        size="sm"
                        @click="query = ''"
                    >
                        {{ trans('labels.cancel') }}
                    </Button>
                    <Button
                        type="button"
                        size="sm"
                        @click="createLabel"
                    >
                        {{ trans('labels.create') }}
                    </Button>
                </div>
            </div>
        </div>
    </div>
</template>
