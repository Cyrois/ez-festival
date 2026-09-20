<script setup>
import { Dialog } from '../dialog';

defineProps({
    open: { type: Boolean, default: false },
    title: { type: String, required: true },
    description: { type: String, default: '' },
    acceptLabel: { type: String, default: '' },
    cancelLabel: { type: String, default: '' },
    busy: { type: Boolean, default: false },
    class: { type: [String, Object, Array], default: '' },
});

const emit = defineEmits(['update:open', 'accept', 'cancel']);
</script>

<template>
    <Dialog
        :open="open"
        :title="title"
        :description="description"
        :confirm-label="acceptLabel"
        :cancel-label="cancelLabel"
        confirm-variant="primary"
        :busy="busy"
        :class="$props.class"
        @update:open="emit('update:open', $event)"
        @cancel="emit('cancel')"
        @confirm="emit('accept')"
    >
        <slot />
    </Dialog>
</template>
