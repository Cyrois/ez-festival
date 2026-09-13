<script setup>
import { computed } from 'vue';
import { useFlashToast } from '../../../composables/useFlashToast';
import { cn } from '../../../lib/utils';

const { message, title, variant, visible, dismiss } = useFlashToast();

const iconWrapClass = computed(() => {
    if (variant.value === 'success') {
        return 'bg-success';
    }
    if (variant.value === 'error') {
        return 'bg-danger';
    }
    return 'bg-secondary';
});

const iconGlyph = computed(() => {
    if (variant.value === 'success') {
        return '✓';
    }
    if (variant.value === 'error') {
        return '×';
    }
    return 'i';
});

const role = computed(() => (variant.value === 'error' ? 'alert' : 'status'));
</script>

<template>
    <div
        v-if="visible"
        :class="
            cn(
                'fixed top-3 right-3 z-50 flex w-full max-w-sm items-start gap-2.5 rounded-[10px] border border-line bg-ground px-3.5 py-3 text-charcoal shadow-[0_4px_16px_rgba(0,0,0,0.08)]',
            )
        "
        :role="role"
    >
        <div
            :class="
                cn(
                    'flex h-[22px] w-[22px] shrink-0 items-center justify-center rounded-full text-[11px] font-bold text-white',
                    iconWrapClass,
                )
            "
            aria-hidden="true"
        >
            {{ iconGlyph }}
        </div>
        <div class="min-w-0 flex-1">
            <strong
                v-if="title"
                class="mb-0.5 block text-[13px] font-bold"
            >
                {{ title }}
            </strong>
            <span
                :class="
                    title
                        ? 'block text-xs leading-snug text-muted'
                        : 'block text-[13px] leading-snug font-bold'
                "
            >
                {{ message }}
            </span>
        </div>
        <button
            type="button"
            class="shrink-0 cursor-pointer border-none bg-transparent p-0 text-base leading-none text-muted hover:text-charcoal"
            :aria-label="$t('ui.toast.dismiss')"
            @click="dismiss"
        >
            ×
        </button>
    </div>
</template>
