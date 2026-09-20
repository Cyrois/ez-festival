<script setup>
import { computed } from 'vue';
import { cn } from '../../../lib/utils';
import { Button } from '../button';
import { Icon } from '../icon';

const props = defineProps({
    icon: {
        type: [String, Array, Object],
        required: true,
    },
    label: {
        type: String,
        required: true,
    },
    tone: {
        type: String,
        default: 'default',
        validator: (value) => ['default', 'edit', 'delete'].includes(value),
    },
    disabled: {
        type: Boolean,
        default: false,
    },
    loading: {
        type: Boolean,
        default: false,
    },
    href: {
        type: String,
        default: null,
    },
    class: {
        type: [String, Object, Array],
        default: '',
    },
});

const variant = computed(() => {
    if (props.tone === 'edit') {
        return 'outline-secondary';
    }

    if (props.tone === 'delete') {
        return 'outline-danger';
    }

    return 'outline';
});

const classes = computed(() => cn('h-8 w-8', props.class));
</script>

<template>
    <Button
        type="button"
        :variant="variant"
        size="icon"
        :disabled="disabled"
        :loading="loading"
        :aria-label="label"
        :title="label"
        :class="classes"
        :href="href"
    >
        <Icon
            :name="icon"
            size="sm"
        />
    </Button>
</template>
