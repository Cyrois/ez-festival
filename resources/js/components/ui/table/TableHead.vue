<script setup>
import { computed } from 'vue';
import { cn } from '../../../lib/utils';

const props = defineProps({
    sortable: {
        type: Boolean,
        default: false,
    },
    sortDirection: {
        type: String,
        default: 'none',
    },
    class: {
        type: [String, Object, Array],
        default: '',
    },
});

const emit = defineEmits(['sort']);

const ariaSort = computed(() => {
    if (!props.sortable) {
        return undefined;
    }

    if (props.sortDirection === 'asc') {
        return 'ascending';
    }

    if (props.sortDirection === 'desc') {
        return 'descending';
    }

    return 'none';
});

const sortGlyph = computed(() => {
    if (props.sortDirection === 'asc') {
        return '↑';
    }

    if (props.sortDirection === 'desc') {
        return '↓';
    }

    return '↕';
});

const classes = computed(() =>
    cn(
        'bg-page px-4 py-3 text-xs font-bold tracking-wide text-muted uppercase',
        props.class,
    ),
);

const onSort = () => {
    if (!props.sortable) {
        return;
    }

    emit('sort');
};
</script>

<template>
    <th
        :class="classes"
        :aria-sort="ariaSort"
    >
        <button
            v-if="sortable"
            type="button"
            class="inline-flex cursor-pointer items-center gap-1 text-inherit uppercase focus-visible:ring-[3px] focus-visible:ring-primary/35 focus-visible:outline-none"
            @click="onSort"
        >
            <slot />
            <span
                class="text-[10px] font-bold tracking-normal text-muted normal-case"
                aria-hidden="true"
            >
                {{ sortGlyph }}
            </span>
        </button>
        <template v-else>
            <slot />
        </template>
    </th>
</template>
