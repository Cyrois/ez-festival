<script setup>
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from 'vue';
import { cn } from '../../../lib/utils';
import { Button } from '../button';
import { Icon } from '../icon';

const props = defineProps({
    focusTrap: {
        type: Boolean,
        default: false,
    },
    role: {
        type: String,
        default: 'alertdialog',
    },
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
    confirmDisabled: {
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
    cancelAlignStart: {
        type: Boolean,
        default: false,
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
const panel = ref(null);
let previousFocus;
const focusInside = () => {
    if (!props.focusTrap) return;
    previousFocus = document.activeElement;
    nextTick(() =>
        panel.value
            ?.querySelector('input:not([disabled]), button:not([disabled])')
            ?.focus(),
    );
};
const restoreFocus = () => {
    if (props.focusTrap && previousFocus?.isConnected) previousFocus.focus();
};

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
    if (event.key === 'Tab' && props.open && props.focusTrap) {
        const controls = [
            ...(panel.value?.querySelectorAll(
                'button:not([disabled]), input:not([disabled]), a[href], [tabindex="0"]',
            ) ?? []),
        ].filter((element) => element.getClientRects().length > 0);
        const first = controls[0];
        const last = controls.at(-1);
        const outside = !panel.value?.contains(document.activeElement);
        if (event.shiftKey && (document.activeElement === first || outside)) {
            event.preventDefault();
            last?.focus();
        } else if (
            !event.shiftKey &&
            (document.activeElement === last || outside)
        ) {
            event.preventDefault();
            first?.focus();
        }
    }
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
        if (isOpen) focusInside();
        else restoreFocus();
    },
);

onMounted(() => {
    window.addEventListener('keydown', onKeydown);
    if (props.open) focusInside();
});

onUnmounted(() => {
    window.removeEventListener('keydown', onKeydown);
    restoreFocus();
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
                ref="panel"
                :role="role"
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
                    <div class="min-w-0">
                        <h2
                            v-if="title"
                            id="ui-dialog-title"
                            class="m-0 text-lg font-bold"
                        >
                            {{ title }}
                        </h2>
                        <p
                            v-if="$slots.subtitle"
                            class="mt-1 text-sm text-muted"
                        >
                            <slot name="subtitle" />
                        </p>
                    </div>
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
                    <div
                        :class="
                            sectioned ? '' : 'min-h-0 flex-1 overflow-y-auto'
                        "
                    >
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
                    <div
                        v-if="$slots['footer-hint']"
                        class="text-sm text-muted sm:mr-auto sm:self-center"
                    >
                        <slot name="footer-hint" />
                    </div>
                    <Button
                        v-if="showCancel"
                        type="button"
                        :variant="cancelVariant"
                        class="min-h-11 w-full sm:w-auto"
                        :class="{ 'sm:mr-auto': cancelAlignStart }"
                        :disabled="busy"
                        @click="close"
                    >
                        {{ cancelLabel || $t('ui.dialog.cancel') }}
                    </Button>
                    <slot name="footer-actions" />
                    <Button
                        v-if="showConfirm"
                        type="button"
                        class="min-h-11 w-full sm:w-auto"
                        :variant="confirmVariant"
                        :loading="busy"
                        :disabled="busy || confirmDisabled"
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
