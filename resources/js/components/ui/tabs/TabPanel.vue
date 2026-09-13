<script setup>
import { computed, inject } from 'vue';
import { cn } from '../../../lib/utils';
import { TABS_KEY } from './tabsContext';

const props = defineProps({
    value: {
        type: [String, Number],
        required: true,
    },
    class: {
        type: [String, Object, Array],
        default: '',
    },
});

const tabs = inject(TABS_KEY, null);

const isActive = computed(() => tabs?.value?.value === props.value);

const classes = computed(() => cn('pt-4 text-sm text-charcoal', props.class));
</script>

<template>
    <div
        v-show="isActive"
        :id="`tabpanel-${value}`"
        role="tabpanel"
        :aria-labelledby="`tab-${value}`"
        :hidden="!isActive"
        :class="classes"
    >
        <slot />
    </div>
</template>
