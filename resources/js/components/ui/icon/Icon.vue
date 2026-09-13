<script setup>
import { computed } from 'vue';
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome';
import { cn } from '../../../lib/utils';

const props = defineProps({
    name: {
        type: [String, Array, Object],
        required: true,
    },
    size: {
        type: String,
        default: 'md',
    },
    fixedWidth: {
        type: Boolean,
        default: false,
    },
    class: {
        type: [String, Object, Array],
        default: '',
    },
});

const sizeClass = computed(() => {
    const sizes = {
        xs: 'text-[10px]',
        sm: 'text-xs',
        md: 'text-sm',
        lg: 'text-base',
        xl: 'text-lg',
    };

    return sizes[props.size] ?? null;
});
</script>

<template>
    <FontAwesomeIcon
        :icon="name"
        :fixed-width="fixedWidth"
        :class="
            cn('inline-block shrink-0 leading-none', sizeClass, props.class)
        "
        aria-hidden="true"
    />
</template>
