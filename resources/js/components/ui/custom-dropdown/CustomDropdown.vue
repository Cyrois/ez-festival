<script setup>
import { computed, nextTick, onMounted, onUnmounted, ref, useAttrs } from 'vue';
import { trans } from 'laravel-vue-i18n';
import { Input } from '../input';
import { cn } from '../../../lib/utils';
import { Icon } from '../icon';
import { Tag } from '../tag';
import { Checkbox } from '../checkbox';
import { Avatar } from '../avatar';

defineOptions({
    inheritAttrs: false,
});

const props = defineProps({
    modelValue: {
        type: [String, Number, Array],
        default: '',
    },
    multiple: { type: Boolean, default: false },
    showSelected: { type: Boolean, default: true },
    matchTriggerWidth: { type: Boolean, default: false },
    searchPlaceholder: { type: String, default: '' },
    footer: { type: String, default: '' },
    groupHints: { type: Object, default: () => ({}) },
    actionLabel: { type: String, default: '' },
    actionHint: { type: String, default: '' },
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

const emit = defineEmits(['update:modelValue', 'action']);

const attrs = useAttrs();
const root = ref(null);
const menu = ref(null);
const searchInput = ref(null);
const query = ref('');
const searchable = computed(() => props.items.length >= 5);
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
const selected = (item) =>
    props.multiple
        ? Array.isArray(props.modelValue) &&
          props.modelValue.includes(item.value)
        : item.value === props.modelValue;
const selectedItems = computed(() => props.items.filter(selected));
const richItems = computed(
    () => props.multiple || props.items.some((item) => item.group),
);
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
        richItems.value &&
            !props.matchTriggerWidth &&
            !insideModal.value &&
            'min-w-[min(18rem,calc(100vw-2rem))]',
        insideModal.value ? 'fixed z-[60]' : 'absolute z-40 mt-1',
    ),
);

const focusOption = (index) => {
    nextTick(() =>
        menu.value?.querySelectorAll('[data-dropdown-option]')[index]?.focus(),
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

    if (!root.value) {
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

    if (!insideModal.value) {
        const width = Math.min(
            Math.max(rect.width, props.matchTriggerWidth ? 0 : 288),
            window.innerWidth - viewportPadding * 2,
        );
        menuStyle.value = richItems.value
            ? {
                  left:
                      Math.min(
                          0,
                          window.innerWidth -
                              rect.left -
                              width -
                              viewportPadding,
                      ) + 'px',
                  ...(opensAbove
                      ? { bottom: 'calc(100% + 4px)', top: 'auto' }
                      : {}),
              }
            : {};
        return;
    }

    menuStyle.value = {
        left: Math.max(viewportPadding, rect.left) + 'px',
        width:
            Math.min(
                Math.max(
                    rect.width,
                    richItems.value && !props.matchTriggerWidth ? 288 : 0,
                ),
                window.innerWidth - viewportPadding * 2,
            ) + 'px',
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

    if (props.disabled) return;
    emit(
        'update:modelValue',
        props.multiple
            ? selected(item)
                ? props.modelValue.filter((value) => value !== item.value)
                : [...props.modelValue, item.value]
            : item.value,
    );
    if (!props.multiple) close();
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
    root.value?.querySelector('[aria-haspopup="listbox"]')?.focus();
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
            v-if="!multiple || !showSelected"
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
            <span :class="(multiple || !selectedItem) && 'text-muted'">
                {{ (!multiple && selectedItem?.title) || placeholder }}
            </span>
            <Icon
                :name="['fas', 'chevron-down']"
                size="sm"
                :class="open ? 'rotate-180' : ''"
            />
        </button>
        <div
            v-else
            :class="[
                triggerClasses,
                'h-auto min-h-10 flex-wrap gap-1 py-1',
                disabled && 'cursor-not-allowed opacity-45',
            ]"
        >
            <div class="flex w-full min-w-0 flex-wrap gap-1">
                <Tag
                    v-for="item in selectedItems"
                    :key="item.value"
                    :name="item.title"
                    class="max-w-full"
                    :removable="!disabled"
                    :remove-label="
                        trans('dropdown.remove', { name: item.title })
                    "
                    @remove="select(item)"
                >
                    <template
                        v-if="item.avatar"
                        #leading
                    >
                        <Avatar
                            :name="item.avatar"
                            size="xs"
                        />
                    </template>
                </Tag>
                <button
                    v-if="!selectedItems.length"
                    type="button"
                    class="flex-1 text-left text-muted"
                    :disabled="disabled"
                    @click="toggle"
                    @keydown="onTriggerKeydown"
                >
                    {{ placeholder }}
                </button>
            </div>
            <button
                type="button"
                class="ml-auto inline-flex min-h-6 min-w-6 items-center justify-center rounded-lg focus-visible:outline-primary"
                :disabled="disabled"
                :aria-expanded="open"
                :aria-invalid="invalid ? 'true' : undefined"
                aria-haspopup="listbox"
                v-bind="attrs"
                @click="toggle"
                @keydown="onTriggerKeydown"
            >
                <Icon :name="['fas', 'chevron-down']" />
            </button>
        </div>
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
                        :placeholder="
                            searchPlaceholder ||
                            trans('dropdown.search_placeholder')
                        "
                        :aria-label="
                            searchPlaceholder ||
                            trans('dropdown.search_placeholder')
                        "
                        @keydown="onSearchKeydown"
                    />
                </div>
                <button
                    v-if="actionLabel"
                    type="button"
                    class="shrink-0 border-b border-line px-3 py-2 text-left text-sm text-primary focus-visible:outline-primary"
                    :disabled="disabled"
                    @click="!disabled && emit('action')"
                >
                    {{ actionLabel }}
                    <span
                        v-if="actionHint"
                        class="mt-1 block text-xs text-muted"
                        >{{ actionHint }}</span
                    >
                </button>
                <div
                    class="min-h-0 overflow-y-auto"
                    role="listbox"
                    :aria-multiselectable="multiple ? 'true' : undefined"
                >
                    <template v-if="filteredItems.length">
                        <template
                            v-for="(item, index) in filteredItems"
                            :key="item.value"
                        >
                            <div
                                v-if="
                                    item.group &&
                                    item.group !==
                                        filteredItems[index - 1]?.group
                                "
                                class="px-3 pt-2 pb-1 text-xs font-semibold text-muted"
                            >
                                {{ item.group }}
                                <span
                                    v-if="groupHints[item.group]"
                                    class="mt-1 block font-normal"
                                    >{{ groupHints[item.group] }}</span
                                >
                            </div>
                            <component
                                :is="multiple ? 'div' : 'button'"
                                :type="multiple ? undefined : 'button'"
                                :tabindex="
                                    multiple
                                        ? item.disabled
                                            ? -1
                                            : 0
                                        : undefined
                                "
                                data-dropdown-option
                                class="flex w-full flex-col px-3 py-2 text-left outline-none hover:bg-page focus:bg-page"
                                :class="[
                                    selected(item) && 'bg-primary/10',
                                    item.disabled &&
                                        'cursor-not-allowed opacity-45',
                                ]"
                                :aria-selected="selected(item)"
                                :disabled="item.disabled"
                                :aria-disabled="
                                    item.disabled || disabled
                                        ? 'true'
                                        : undefined
                                "
                                role="option"
                                @click="select(item)"
                                @keydown="onOptionKeydown($event, item, index)"
                            >
                                <div class="flex w-full items-center gap-2">
                                    <Checkbox
                                        v-if="multiple"
                                        :model-value="selected(item)"
                                        :disabled="item.disabled || disabled"
                                        :aria-label="item.title"
                                        tabindex="-1"
                                        @click.stop
                                        @update:model-value="select(item)"
                                    />
                                    <Avatar
                                        v-if="item.avatar"
                                        :name="item.avatar"
                                        size="sm"
                                        aria-hidden="true"
                                    />
                                    <div class="min-w-0 flex-1 text-left">
                                        <div
                                            class="text-sm font-medium text-charcoal"
                                        >
                                            {{ item.title }}
                                        </div>
                                        <div
                                            v-if="item.description"
                                            class="mt-0.5 text-xs text-muted"
                                        >
                                            {{ item.description }}
                                        </div>
                                        <div
                                            v-if="item.note"
                                            class="mt-1 text-xs text-muted"
                                        >
                                            {{ item.note }}
                                        </div>
                                    </div>
                                    <span
                                        v-if="item.trailing"
                                        class="text-xs text-muted"
                                        >{{ item.trailing }}</span
                                    >
                                </div>
                            </component>
                        </template>
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
                <p
                    v-if="footer"
                    class="m-0 shrink-0 border-t border-line px-3 py-2 text-xs text-muted"
                >
                    {{ footer }}
                </p>
            </div>
        </Teleport>
    </div>
</template>
