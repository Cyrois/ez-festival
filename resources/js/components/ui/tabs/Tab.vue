<script setup>
import { computed, inject } from 'vue';
import { Link } from '@inertiajs/vue3';
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
    href: {
        type: String,
        default: '',
    },
    class: {
        type: [String, Object, Array],
        default: '',
    },
});

const tabs = inject(TABS_KEY, null);

const isActive = computed(() => tabs?.value?.value === props.value);
const navigation = computed(() => tabs?.value?.navigation === true);
const tag = computed(() =>
    navigation.value ? (props.disabled ? 'span' : Link) : 'button',
);

const tabId = computed(
    () => `tab-${tabs?.value?.instanceId ?? 'tabs'}-${props.value}`,
);

const panelId = computed(
    () => `tabpanel-${tabs?.value?.instanceId ?? 'tabs'}-${props.value}`,
);

const classes = computed(() =>
    cn(
        '-mb-px inline-flex cursor-pointer items-center justify-center border-b-2 px-3 py-2 text-sm font-bold no-underline transition-colors',
        'focus-visible:outline-none focus-visible:ring-[3px] focus-visible:ring-primary/35',
        isActive.value
            ? 'border-primary text-primary'
            : 'border-transparent text-muted hover:text-charcoal',
        props.disabled && 'pointer-events-none opacity-45',
        props.class,
    ),
);

const onSelect = () => {
    if (navigation.value || props.disabled || !tabs?.value) {
        return;
    }

    tabs.value.setValue(props.value);
};
</script>

<template>
    <component
        :is="tag"
        :id="navigation ? undefined : tabId"
        :href="navigation && !disabled ? href : undefined"
        :type="navigation ? undefined : 'button'"
        :role="navigation ? undefined : 'tab'"
        :aria-selected="navigation ? undefined : isActive ? 'true' : 'false'"
        :aria-controls="navigation ? undefined : panelId"
        :aria-current="navigation && isActive ? 'page' : undefined"
        :aria-disabled="navigation && disabled ? 'true' : undefined"
        :tabindex="navigation ? undefined : isActive ? 0 : -1"
        :disabled="navigation ? undefined : disabled"
        :class="classes"
        @click="onSelect"
    >
        <slot />
    </component>
</template>
