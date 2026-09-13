<script setup>
import { computed, inject } from 'vue';
import { cn } from '../../../lib/utils';
import { TABS_KEY } from './tabsContext';

const props = defineProps({
    value: {
        type: [String, Number],
        required: true,
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

const tabs = inject(TABS_KEY, null);

const isActive = computed(() => tabs?.value?.value === props.value);

const tabId = computed(
    () => `tab-${tabs?.value?.instanceId ?? 'tabs'}-${props.value}`,
);

const panelId = computed(
    () => `tabpanel-${tabs?.value?.instanceId ?? 'tabs'}-${props.value}`,
);

const classes = computed(() =>
    cn(
        '-mb-px inline-flex items-center justify-center border-b-2 px-3 py-2 text-sm font-bold transition-colors',
        'focus-visible:outline-none focus-visible:ring-[3px] focus-visible:ring-primary/35',
        isActive.value
            ? 'border-primary text-primary'
            : 'border-transparent text-muted hover:text-charcoal',
        props.disabled && 'pointer-events-none opacity-45',
        props.class,
    ),
);

const onSelect = () => {
    if (props.disabled || !tabs?.value) {
        return;
    }

    tabs.value.setValue(props.value);
};
</script>

<template>
    <button
        :id="tabId"
        type="button"
        role="tab"
        :aria-selected="isActive ? 'true' : 'false'"
        :aria-controls="panelId"
        :tabindex="isActive ? 0 : -1"
        :disabled="disabled"
        :class="classes"
        @click="onSelect"
    >
        <slot />
    </button>
</template>
