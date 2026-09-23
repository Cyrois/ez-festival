<script setup>
import { computed } from 'vue';
import { cn } from '../../../lib/utils';
import { fallbackLabelToken, labelTokens } from '../../../lib/labelTokens';
import { Icon } from '../icon';

const props = defineProps({
    name: {
        type: String,
        required: true,
    },
    color: {
        type: String,
        default: 'slate',
    },
    class: {
        type: [String, Object, Array],
        default: '',
    },
    removable: {
        type: Boolean,
        default: false,
    },
    removeLabel: {
        type: String,
        default: '',
    },
});

const emit = defineEmits(['remove']);

const token = computed(
    () => labelTokens[props.color] ?? labelTokens[fallbackLabelToken],
);

const classes = computed(() =>
    cn(
        'inline-flex items-center gap-1.5 rounded-full border px-2.5 py-0.5 text-xs font-bold',
        token.value.classes,
        props.class,
    ),
);

const dotClass = computed(() =>
    cn('h-1.5 w-1.5 shrink-0 rounded-full', token.value.dot),
);
</script>

<template>
    <span :class="classes">
        <span
            :class="dotClass"
            aria-hidden="true"
        />
        <span>{{ name }}</span>
        <button
            v-if="removable"
            type="button"
            class="-mr-1 inline-flex h-4 w-4 shrink-0 cursor-pointer items-center justify-center rounded-full text-current hover:bg-charcoal/10 focus-visible:ring-2 focus-visible:ring-primary/35 focus-visible:outline-none"
            :aria-label="removeLabel"
            @click.stop="emit('remove')"
        >
            <Icon
                :name="['fas', 'xmark']"
                class="text-[9px]"
            />
        </button>
    </span>
</template>
