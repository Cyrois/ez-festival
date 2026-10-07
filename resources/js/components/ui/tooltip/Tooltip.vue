<script setup>
import { nextTick, onMounted, onUnmounted, ref, useId } from 'vue';

defineProps({ label: { type: String, required: true } });
const trigger = ref(null);
const content = ref(null);
const visible = ref(false);
const position = ref({});
const id = useId();
const place = () => {
    if (!visible.value || !trigger.value || !content.value) return;
    const anchor = trigger.value.getBoundingClientRect();
    const box = content.value.getBoundingClientRect();
    const gap = 8;
    const above = anchor.top - box.height - gap;
    position.value = {
        left:
            Math.max(
                gap,
                Math.min(anchor.left, window.innerWidth - box.width - gap),
            ) + 'px',
        top:
            Math.max(
                gap,
                Math.min(
                    above >= gap ? above : anchor.bottom + gap,
                    window.innerHeight - box.height - gap,
                ),
            ) + 'px',
    };
};
const show = async () => {
    visible.value = true;
    await nextTick();
    place();
};
const hide = () => {
    visible.value = false;
};
onMounted(() => {
    document.addEventListener('scroll', place, true);
    window.addEventListener('resize', place);
});
onUnmounted(() => {
    document.removeEventListener('scroll', place, true);
    window.removeEventListener('resize', place);
});
</script>

<template>
    <span
        ref="trigger"
        class="inline-flex shrink-0 rounded-lg focus-visible:outline-2 focus-visible:outline-primary"
        tabindex="0"
        :aria-label="label"
        :aria-describedby="visible ? id : undefined"
        @mouseenter="show"
        @mouseleave="hide"
        @focus="show"
        @blur="hide"
        @keydown.esc.prevent.stop="hide"
    >
        <slot />
    </span>
    <Teleport to="body">
        <div
            v-if="visible"
            :id="id"
            ref="content"
            role="tooltip"
            class="pointer-events-none fixed z-50 w-64 max-w-[calc(100vw-1rem)] rounded-lg bg-charcoal px-3 py-2 text-xs text-white shadow-lg"
            :style="position"
        >
            <slot name="content" />
        </div>
    </Teleport>
</template>
