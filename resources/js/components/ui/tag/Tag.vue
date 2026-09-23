<script setup>
import { computed } from 'vue';
import { cn } from '../../../lib/utils';
import { Icon } from '../icon';

const tokenClasses = {
    teal: 'border-label-teal/20 bg-label-teal/10 text-label-teal',
    soft_blue:
        'border-label-soft-blue/20 bg-label-soft-blue/10 text-label-soft-blue',
    success: 'border-success/20 bg-success/10 text-success',
    warning: 'border-warning/20 bg-warning/10 text-warning',
    danger: 'border-danger/20 bg-danger/10 text-danger',
    violet: 'border-label-violet/20 bg-label-violet/10 text-label-violet',
    sky: 'border-label-sky/20 bg-label-sky/10 text-label-sky',
    rose: 'border-label-rose/20 bg-label-rose/10 text-label-rose',
    slate: 'border-label-slate/20 bg-label-slate/10 text-label-slate',
    charcoal: 'border-charcoal/20 bg-charcoal/10 text-charcoal',
};

const tokenDot = {
    teal: 'bg-label-teal',
    soft_blue: 'bg-label-soft-blue',
    success: 'bg-success',
    warning: 'bg-warning',
    danger: 'bg-danger',
    violet: 'bg-label-violet',
    sky: 'bg-label-sky',
    rose: 'bg-label-rose',
    slate: 'bg-label-slate',
    charcoal: 'bg-charcoal',
};

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

const isToken = computed(() =>
    Object.prototype.hasOwnProperty.call(tokenClasses, props.color),
);

const classes = computed(() =>
    cn(
        'inline-flex items-center gap-1.5 rounded-full border px-2.5 py-0.5 text-xs font-bold',
        isToken.value ? tokenClasses[props.color] : tokenClasses.slate,
        props.class,
    ),
);

const dotClass = computed(() =>
    cn(
        'h-1.5 w-1.5 shrink-0 rounded-full',
        isToken.value ? tokenDot[props.color] : tokenDot.slate,
    ),
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
