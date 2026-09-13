<script setup>
import { computed, useId } from 'vue';
import { cn } from '../../../lib/utils';

const props = defineProps({
    label: {
        type: String,
        default: '',
    },
    error: {
        type: String,
        default: '',
    },
    hint: {
        type: String,
        default: '',
    },
    htmlFor: {
        type: String,
        default: '',
    },
    required: {
        type: Boolean,
        default: false,
    },
    class: {
        type: [String, Object, Array],
        default: '',
    },
});

const generatedId = useId();
const controlId = computed(() => props.htmlFor || generatedId);
const hasError = computed(() => Boolean(props.error));
</script>

<template>
    <div :class="cn('flex flex-col gap-1.5', props.class)">
        <label
            v-if="label"
            :for="controlId"
            class="text-xs font-bold text-charcoal"
        >
            {{ label
            }}<span
                v-if="required"
                class="ml-0.5 text-danger"
                aria-hidden="true"
                >*</span
            >
        </label>
        <slot
            :id="controlId"
            :invalid="hasError"
        />
        <p
            v-if="error"
            class="m-0 text-xs leading-snug text-danger"
            role="alert"
        >
            {{ error }}
        </p>
        <p
            v-else-if="hint"
            class="m-0 text-xs leading-snug text-muted"
        >
            {{ hint }}
        </p>
    </div>
</template>
