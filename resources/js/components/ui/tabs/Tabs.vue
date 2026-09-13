<script setup>
import { computed, provide, useId } from 'vue';
import { cn } from '../../../lib/utils';
import { TABS_KEY } from './tabsContext';

const props = defineProps({
    modelValue: {
        type: [String, Number],
        default: '',
    },
    class: {
        type: [String, Object, Array],
        default: '',
    },
});

const emit = defineEmits(['update:modelValue']);

const instanceId = useId();

const setValue = (value) => {
    emit('update:modelValue', value);
};

provide(
    TABS_KEY,
    computed(() => ({
        value: props.modelValue,
        setValue,
        instanceId,
    })),
);

const classes = computed(() => cn('w-full', props.class));
</script>

<template>
    <div :class="classes">
        <slot />
    </div>
</template>
