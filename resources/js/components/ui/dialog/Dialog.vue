<script setup>
import { computed, onMounted, onUnmounted, watch } from 'vue';
import { cn } from '../../../lib/utils';
import { Button } from '../button';

const props = defineProps({
    open: {
        type: Boolean,
        default: false,
    },
    title: {
        type: String,
        default: '',
    },
    description: {
        type: String,
        default: '',
    },
    confirmLabel: {
        type: String,
        default: 'Confirm',
    },
    cancelLabel: {
        type: String,
        default: 'Cancel',
    },
    confirmVariant: {
        type: String,
        default: 'danger',
    },
    busy: {
        type: Boolean,
        default: false,
    },
    class: {
        type: [String, Object, Array],
        default: '',
    },
});

const emit = defineEmits(['update:open', 'confirm', 'cancel']);

const panelClass = computed(() =>
    cn(
        'relative z-10 w-full max-w-md rounded-xl border border-line bg-ground p-5 text-charcoal shadow-toast',
        props.class,
    ),
);

const close = () => {
    if (props.busy) {
        return;
    }
    emit('update:open', false);
    emit('cancel');
};

const confirm = () => {
    if (props.busy) {
        return;
    }
    emit('confirm');
};

const onKeydown = (event) => {
    if (event.key === 'Escape' && props.open) {
        close();
    }
};

watch(
    () => props.open,
    (isOpen) => {
        if (typeof document === 'undefined') {
            return;
        }
        document.body.style.overflow = isOpen ? 'hidden' : '';
    },
);

onMounted(() => {
    window.addEventListener('keydown', onKeydown);
});

onUnmounted(() => {
    window.removeEventListener('keydown', onKeydown);
    if (typeof document !== 'undefined') {
        document.body.style.overflow = '';
    }
});
</script>

<template>
    <Teleport to="body">
        <div
            v-if="open"
            class="fixed inset-0 z-50 flex items-center justify-center px-4"
            role="presentation"
        >
            <div
                class="absolute inset-0 bg-charcoal/40"
                aria-hidden="true"
                @click="close"
            />
            <div
                role="alertdialog"
                aria-modal="true"
                :aria-labelledby="title ? 'ui-dialog-title' : undefined"
                :aria-describedby="
                    description ? 'ui-dialog-description' : undefined
                "
                :class="panelClass"
            >
                <h2
                    v-if="title"
                    id="ui-dialog-title"
                    class="m-0 mb-2 text-base font-bold"
                >
                    {{ title }}
                </h2>
                <p
                    v-if="description"
                    id="ui-dialog-description"
                    class="m-0 text-sm leading-snug text-muted"
                >
                    {{ description }}
                </p>
                <slot />
                <div class="mt-5 flex justify-end gap-3">
                    <Button
                        type="button"
                        variant="outline"
                        :disabled="busy"
                        @click="close"
                    >
                        {{ cancelLabel }}
                    </Button>
                    <Button
                        type="button"
                        :variant="confirmVariant"
                        :loading="busy"
                        :disabled="busy"
                        @click="confirm"
                    >
                        {{ confirmLabel }}
                    </Button>
                </div>
            </div>
        </div>
    </Teleport>
</template>
