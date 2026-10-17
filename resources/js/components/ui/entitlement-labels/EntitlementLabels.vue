<script setup>
import {
    computed,
    nextTick,
    onMounted,
    onUnmounted,
    ref,
    useId,
    watch,
} from 'vue';
import { trans } from 'laravel-vue-i18n';
import { Tag } from '../tag';
import { Icon } from '../icon';
import { fitLabels } from './fitLabels';

const props = defineProps({
    labels: { type: Array, default: () => [] },
    itemName: { type: String, default: '' },
});
const root = ref(null);
const track = ref(null);
const measurement = ref(null);
const trigger = ref(null);
const popup = ref(null);
const visibleCount = ref(props.labels.length);
const open = ref(false);
const popupId = useId();
const position = ref({});
const visible = computed(() => props.labels.slice(0, visibleCount.value));
const hidden = computed(() => props.labels.slice(visibleCount.value));
const popupLabel = computed(() =>
    trans('entitlement_labels.title', { name: props.itemName }),
);
const chipClass =
    'inline-flex shrink-0 items-center rounded-full border border-line bg-ground px-2 py-0.5 text-xs font-bold text-muted';
let observer;
let media;
let disposed = false;

const close = () => {
    if (!open.value) return;
    open.value = false;
    popup.value?.hidePopover?.();
    trigger.value?.focus({ preventScroll: true });
};
const placePopup = () => {
    if (!open.value || !trigger.value || !popup.value) return;
    const anchor = trigger.value.getBoundingClientRect();
    const rect = popup.value.getBoundingClientRect();
    const below = window.innerHeight - anchor.bottom - 8;
    const above = anchor.top - 8;
    const opensAbove = rect.height > below && above > below;
    position.value = {
        left: `${Math.max(8, Math.min(anchor.right - rect.width, window.innerWidth - rect.width - 8))}px`,
        top: `${opensAbove ? Math.max(8, anchor.top - rect.height - 4) : anchor.bottom + 4}px`,
        maxHeight: `${Math.max(48, opensAbove ? above : below) - 4}px`,
    };
};
const measure = () => {
    if (disposed || !root.value || !measurement.value) return;
    if (!media?.matches) {
        visibleCount.value = props.labels.length;
        close();
        return;
    }
    const widths = [
        ...measurement.value.querySelectorAll('[data-label-measure]'),
    ].map((el) => el.getBoundingClientRect().width);
    const chips = [
        0,
        ...[...measurement.value.querySelectorAll('[data-chip-measure]')].map(
            (el) => el.getBoundingClientRect().width,
        ),
    ];
    const gap = parseFloat(getComputedStyle(track.value).columnGap) || 6;
    visibleCount.value = fitLabels(
        widths,
        root.value.getBoundingClientRect().width,
        chips,
        gap,
    );
    if (!hidden.value.length) close();
    nextTick(placePopup);
};
const show = async () => {
    if (open.value) return close();
    open.value = true;
    await nextTick();
    popup.value?.showPopover?.();
    placePopup();
    popup.value?.querySelector('button')?.focus({ preventScroll: true });
};
const outside = (event) => {
    if (
        open.value &&
        !popup.value?.contains(event.target) &&
        !trigger.value?.contains(event.target)
    )
        close();
};
const focusLeft = (event) => {
    if (
        open.value &&
        !popup.value?.contains(event.relatedTarget) &&
        event.relatedTarget !== trigger.value
    )
        close();
};
watch(
    () => [props.labels, props.itemName],
    async () => {
        close();
        await nextTick();
        if (root.value) observer?.observe(root.value);
        if (measurement.value) observer?.observe(measurement.value);
        measure();
    },
    { deep: true },
);
onMounted(() => {
    media = window.matchMedia('(max-width: 767px)');
    media.addEventListener('change', measure);
    if (typeof ResizeObserver !== 'undefined') {
        observer = new ResizeObserver(measure);
        if (root.value) observer.observe(root.value);
        if (measurement.value) observer.observe(measurement.value);
    }
    document.fonts?.ready.then(() => {
        if (!disposed) measure();
    });
    document.addEventListener('pointerdown', outside, true);
    document.addEventListener('scroll', placePopup, true);
    window.addEventListener('resize', measure);
    measure();
});
onUnmounted(() => {
    disposed = true;
    popup.value?.hidePopover?.();
    observer?.disconnect();
    media?.removeEventListener('change', measure);
    document.removeEventListener('pointerdown', outside, true);
    document.removeEventListener('scroll', placePopup, true);
    window.removeEventListener('resize', measure);
});
</script>

<template>
    <span
        v-if="labels.length"
        ref="root"
        class="relative block max-w-full min-w-0 md:inline-block"
        data-entitlement-labels
    >
        <span
            ref="track"
            class="flex min-w-0 flex-nowrap items-center gap-1.5 md:flex-wrap"
        >
            <Tag
                v-for="label in visible"
                :key="label.id"
                :name="label.name"
                :color="label.color"
                :title="label.name"
                class="max-w-full min-w-0 shrink-0 first:shrink md:shrink"
            />
            <button
                v-if="hidden.length"
                ref="trigger"
                type="button"
                :class="[
                    chipClass,
                    'pointer-events-auto cursor-pointer focus-visible:outline-primary',
                ]"
                :aria-label="
                    trans('entitlement_labels.hidden', {
                        count: hidden.length,
                        labels: hidden.map((label) => label.name).join(', '),
                    })
                "
                :aria-expanded="open"
                :aria-controls="open ? popupId : undefined"
                aria-haspopup="dialog"
                @pointerdown.stop
                @click.stop="show"
                @keydown.stop
            >
                {{ trans('entitlement_labels.more', { count: hidden.length }) }}
            </button>
        </span>
        <span
            class="pointer-events-none invisible fixed top-0 left-0 h-0 w-0 overflow-hidden"
            aria-hidden="true"
            inert
        >
            <span
                ref="measurement"
                class="flex w-max gap-1.5"
                aria-hidden="true"
                inert
            >
                <Tag
                    v-for="label in labels"
                    :key="label.id"
                    :name="label.name"
                    :color="label.color"
                    class="shrink-0"
                    data-label-measure
                />
                <span
                    v-for="count in labels.length"
                    :key="`chip-${count}`"
                    :class="chipClass"
                    data-chip-measure
                    >{{ trans('entitlement_labels.more', { count }) }}</span
                >
            </span>
        </span>
        <div
            v-if="open"
            :id="popupId"
            ref="popup"
            popover="manual"
            role="dialog"
            :aria-label="popupLabel"
            class="pointer-events-auto fixed inset-auto z-[70] m-0 w-max max-w-[min(20rem,calc(100vw-1rem))] overflow-y-auto rounded-lg border border-line bg-ground p-3 text-charcoal shadow-toast"
            :style="position"
            @click.stop
            @pointerdown.stop
            @keydown.esc.prevent.stop="close"
            @focusout="focusLeft"
        >
            <div class="mb-2 flex items-center justify-between gap-3">
                <span class="text-xs font-bold text-muted">{{
                    popupLabel
                }}</span>
                <button
                    type="button"
                    class="inline-flex h-7 w-7 shrink-0 items-center justify-center rounded-lg text-muted focus-visible:outline-primary"
                    :aria-label="trans('ui.dialog.close')"
                    @click.stop="close"
                >
                    <Icon :name="['fas', 'xmark']" />
                </button>
            </div>
            <div class="flex min-w-0 flex-col items-start gap-1.5">
                <Tag
                    v-for="label in labels"
                    :key="label.id"
                    :name="label.name"
                    :color="label.color"
                    :title="label.name"
                    class="max-w-full"
                />
            </div>
        </div>
    </span>
</template>
