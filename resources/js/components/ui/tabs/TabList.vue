<script setup>
import { computed } from 'vue';
import { cn } from '../../../lib/utils';

const props = defineProps({
    class: {
        type: [String, Object, Array],
        default: '',
    },
});

const classes = computed(() =>
    cn(
        'flex flex-nowrap gap-1 overflow-x-auto overflow-y-hidden border-b border-line [scrollbar-width:none] [&::-webkit-scrollbar]:hidden',
        props.class,
    ),
);

const enabledTabs = (list) =>
    [...list.querySelectorAll('[role="tab"]')].filter(
        (tab) => !tab.disabled && tab.getAttribute('aria-disabled') !== 'true',
    );

const activateTab = (tab) => {
    tab.focus();
    tab.click();
};

const onKeydown = (event) => {
    const keys = ['ArrowLeft', 'ArrowRight', 'Home', 'End'];

    if (!keys.includes(event.key)) {
        return;
    }

    const tabs = enabledTabs(event.currentTarget);

    if (!tabs.length) {
        return;
    }

    const currentIndex = tabs.indexOf(document.activeElement);

    if (currentIndex < 0) {
        return;
    }

    event.preventDefault();

    let nextIndex = currentIndex;

    if (event.key === 'ArrowRight') {
        nextIndex = (currentIndex + 1) % tabs.length;
    } else if (event.key === 'ArrowLeft') {
        nextIndex = (currentIndex - 1 + tabs.length) % tabs.length;
    } else if (event.key === 'Home') {
        nextIndex = 0;
    } else if (event.key === 'End') {
        nextIndex = tabs.length - 1;
    }

    activateTab(tabs[nextIndex]);
};
</script>

<template>
    <div
        role="tablist"
        :class="classes"
        @keydown="onKeydown"
    >
        <slot />
    </div>
</template>
