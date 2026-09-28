<script setup>
import { computed, onMounted, onUnmounted, watch } from 'vue';
import { cn } from '../../../lib/utils';
import { Button } from '../button';
import { Icon } from '../icon';

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
        default: '',
    },
    cancelLabel: {
        type: String,
        default: '',
    },
    confirmVariant: {
        type: String,
        default: 'danger',
    },
    busy: {
        type: Boolean,
        default: false,
    },
    showCancel: {
        type: Boolean,
        default: true,
    },
    showConfirm: {
        type: Boolean,
        default: true,
    },
    // Opt-in layout with a header (title + close button), body, and a
    // shaded footer. Off by default so existing dialogs keep their look.
    sectioned: {
        type: Boolean,
        default: false,
    },
    closeLabel: {
        type: String,
        default: '',
    },
    cancelVariant: {
        type: String,
        default: 'cancel',
    },
    confirmIcon: {
        type: [String, Array, Object],
        default: null,
    },
    class: {
        type: [String, Object, Array],
        default: '',
    },
});

const emit = defineEmits(['update:open', 'confirm', 'cancel']);

const panelClass = computed(() =>
    cn(
        'relative z-10 flex max-h-[90vh] w-full max-w-md flex-col overflow-hidden rounded-t-xl border border-line bg-ground text-charcoal shadow-toast max-md:rounded-b-none sm:rounded-xl',
        props.sectioned ? 'p-0' : 'p-5',
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
            class="fixed inset-0 z-50 flex items-end justify-center px-0 sm:items-center sm:px-4"
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
                <div
                    v-if="sectioned"
                    class="flex shrink-0 items-center justify-between gap-3 border-b border-line px-5 py-4"
                >
                    <h2
                        v-if="title"
                        id="ui-dialog-title"
                        class="m-0 text-lg font-bold"
                    >
                        {{ title }}
                    </h2>
                    <button
                        type="button"
                        class="-mr-1 inline-flex h-8 w-8 cursor-pointer items-center justify-center rounded-lg text-muted transition-colors hover:bg-page hover:text-charcoal focus-visible:ring-[3px] focus-visible:ring-primary/35 focus-visible:outline-none"
                        :aria-label="closeLabel || $t('ui.dialog.close')"
                        :disabled="busy"
                        @click="close"
                    >
                        <Icon :name="['fas', 'xmark']" />
                    </button>
                </div>
                <h2
                    v-else-if="title"
                    id="ui-dialog-title"
                    class="m-0 mb-2 text-base font-bold"
                >
                    {{ title }}
                </h2>
                <div
                    :class="
                        sectioned
                            ? 'min-h-0 overflow-y-auto px-5 py-4'
                            : 'contents'
                    "
                >
                    <p
                        v-if="description || $slots.description"
                        id="ui-dialog-description"
                        :class="
                            sectioned
                                ? 'm-0 text-sm leading-relaxed text-charcoal'
                                : 'm-0 text-sm leading-snug text-muted'
                        "
                    >
                        <slot
                            v-if="$slots.description"
                            name="description"
                        />
                        <template v-else>{{ description }}</template>
                    </p>
                    <div :class="sectioned ? '' : 'min-h-0 overflow-y-auto'">
                        <slot />
                    </div>
                </div>
                <div
                    v-if="showCancel || showConfirm"
                    :class="
                        sectioned
                            ? 'flex shrink-0 flex-col-reverse gap-2 border-t border-line bg-page px-5 py-4 sm:flex-row sm:justify-end sm:gap-3'
                            : 'mt-5 flex shrink-0 flex-col-reverse gap-2 sm:flex-row sm:justify-end sm:gap-3'
                    "
                >
                    <Button
                        v-if="showCancel"
                        type="button"
                        :variant="cancelVariant"
                        class="min-h-11 w-full sm:w-auto"
                        :disabled="busy"
                        @click="close"
                    >
                        {{ cancelLabel || $t('ui.dialog.cancel') }}
                    </Button>
                    <Button
                        v-if="showConfirm"
                        type="button"
                        class="min-h-11 w-full sm:w-auto"
                        :variant="confirmVariant"
                        :loading="busy"
                        :disabled="busy"
                        @click="confirm"
                    >
                        <Icon
                            v-if="confirmIcon"
                            :name="confirmIcon"
                            size="sm"
                        />
                        {{ confirmLabel || $t('ui.dialog.confirm') }}
                    </Button>
                </div>
            </div>
        </div>
    </Teleport>
</template>
