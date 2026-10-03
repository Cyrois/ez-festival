<script setup>
import { computed, nextTick, onMounted, onUnmounted, ref, useAttrs } from 'vue';
import { trans } from 'laravel-vue-i18n';
import { Input } from '../input';
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
    emptyText: {
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
const menu = ref(null);
const searchInput = ref(null);
const query = ref('');
const searchable = computed(() => props.items.length >= 6);
const filteredItems = computed(() => {
    const search = searchable.value
        ? query.value.trim().toLocaleLowerCase()
        : '';

    return search
        ? props.items.filter((item) =>
              [item.title, item.description].some((text) =>
                  String(text ?? '')
                      .toLocaleLowerCase()
                      .includes(search),
              ),
          )
        : props.items;
});
const open = ref(false);
const insideModal = ref(false);
const menuStyle = ref({});
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
const menuClasses = computed(() =>
    cn(
        'flex w-full flex-col overflow-hidden rounded-lg border border-line bg-ground py-1 shadow-toast',
        searchable.value && 'max-h-64',
        insideModal.value ? 'fixed z-[60]' : 'absolute z-40 mt-1',
    ),
);

const focusOption = (index) => {
    nextTick(() =>
        menu.value?.querySelectorAll('[role="option"]')[index]?.focus(),
    );
};

const close = () => {
    open.value = false;
    query.value = '';
};

const updateMenuPosition = () => {
    if (!open.value) {
        return;
    }

    if (!insideModal.value || !root.value) {
        menuStyle.value = {};
        return;
    }

    const rect = root.value.getBoundingClientRect();
    const viewportPadding = 8;
    const gap = 4;
    const maximumHeight = 256;
    const desiredHeight = Math.min(
        menu.value?.scrollHeight ?? props.items.length * 44 + 8,
        maximumHeight,
    );
    const availableBelow =
        window.innerHeight - rect.bottom - gap - viewportPadding;
    const availableAbove = rect.top - gap - viewportPadding;
    const opensAbove =
        availableBelow < desiredHeight && availableAbove > availableBelow;
    const availableHeight = opensAbove ? availableAbove : availableBelow;

    menuStyle.value = {
        left: Math.max(viewportPadding, rect.left) + 'px',
        width:
            Math.min(rect.width, window.innerWidth - viewportPadding * 2) +
            'px',
        maxHeight:
            Math.max(96, Math.min(maximumHeight, availableHeight)) + 'px',
        ...(opensAbove
            ? {
                  bottom: window.innerHeight - rect.top + gap + 'px',
                  top: 'auto',
              }
            : { top: rect.bottom + gap + 'px', bottom: 'auto' }),
    };
};

const toggle = () => {
    if (props.disabled) {
        return;
    }

    insideModal.value = Boolean(root.value?.closest('[aria-modal="true"]'));
    if (open.value) {
        close();
        return;
    }

    open.value = true;

    if (open.value) {
        nextTick(() => {
            updateMenuPosition();
            if (searchable.value) {
                searchInput.value?.$el.focus();
            } else {
                const index = selectedIndex.value;
                focusOption(
                    index >= 0 && !filteredItems.value[index].disabled
                        ? index
                        : filteredItems.value.findIndex(
                              (item) => !item.disabled,
                          ),
                );
            }
        });
    }
};

const select = (item) => {
    if (item.disabled) {
        return;
    }

    emit('update:modelValue', item.value);
    close();
};

const moveFocus = (currentIndex, direction) => {
    for (
        let index = currentIndex + direction;
        index >= 0 && index < filteredItems.value.length;
        index += direction
    ) {
        if (!filteredItems.value[index].disabled) {
            focusOption(index);
            return;
        }
    }

    if (direction < 0 && searchable.value) {
        searchInput.value?.$el.focus();
    }
};

const onSearchKeydown = (event) => {
    if (event.key === 'ArrowDown') {
        event.preventDefault();
        moveFocus(-1, 1);
    }

    if (event.key === 'ArrowUp') {
        event.preventDefault();
        moveFocus(filteredItems.value.length, -1);
    }

    if (event.key === 'Enter') {
        event.preventDefault();
        const item = filteredItems.value.find((item) => !item.disabled);
        if (item) {
            select(item);
        }
    }
};

const onMenuEscape = () => {
    close();
    root.value?.querySelector('button')?.focus();
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

    if (['Enter', ' '].includes(event.key)) {
        event.preventDefault();
        select(item);
    }
};

const onDocumentPointerDown = (event) => {
    if (
        root.value &&
        !root.value.contains(event.target) &&
        !menu.value?.contains(event.target)
    ) {
        close();
    }
};

onMounted(() => {
    document.addEventListener('pointerdown', onDocumentPointerDown);
    document.addEventListener('scroll', updateMenuPosition, true);
    window.addEventListener('resize', updateMenuPosition);
});

onUnmounted(() => {
    document.removeEventListener('pointerdown', onDocumentPointerDown);
    document.removeEventListener('scroll', updateMenuPosition, true);
    window.removeEventListener('resize', updateMenuPosition);
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
        <Teleport
            to="body"
            :disabled="!insideModal"
        >
            <div
                v-if="open"
                ref="menu"
                :class="menuClasses"
                :style="menuStyle"
                @keydown.esc.prevent.stop="onMenuEscape"
            >
                <div
                    v-if="searchable"
                    class="shrink-0 border-b border-line px-2 pt-1 pb-2"
                >
                    <Input
                        ref="searchInput"
                        v-model="query"
                        type="search"
                        :placeholder="trans('dropdown.search_placeholder')"
                        :aria-label="trans('dropdown.search_placeholder')"
                        @keydown="onSearchKeydown"
                    />
                </div>
                <div
                    class="min-h-0 overflow-y-auto"
                    role="listbox"
                >
                    <template v-if="filteredItems.length">
                        <button
                            v-for="(item, index) in filteredItems"
                            :key="item.value"
                            type="button"
                            class="flex w-full flex-col px-3 py-2 text-left outline-none hover:bg-page focus:bg-page"
                            :class="[
                                item.value === modelValue && 'bg-primary/10',
                                item.disabled &&
                                    'cursor-not-allowed opacity-45',
                            ]"
                            :aria-selected="item.value === modelValue"
                            :disabled="item.disabled"
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
                    </template>
                    <p
                        v-else-if="query.trim() || emptyText"
                        class="m-0 px-3 py-2 text-sm text-muted"
                    >
                        {{
                            query.trim()
                                ? trans('dropdown.no_results')
                                : emptyText
                        }}
                    </p>
                </div>
            </div>
        </Teleport>
    </div>
</template>
