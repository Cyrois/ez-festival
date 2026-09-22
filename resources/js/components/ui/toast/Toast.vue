<script setup>
import { computed } from 'vue';
import { useFlashToast } from '../../../composables/useFlashToast';
import { cn } from '../../../lib/utils';
import { Icon } from '../icon';

const { message, title, variant, visible, dismiss } = useFlashToast();

const toastMeta = computed(() => {
    if (variant.value === 'success') {
        return {
            wrap: 'bg-success',
            icon: ['fas', 'check'],
        };
    }
    if (variant.value === 'error') {
        return {
            wrap: 'bg-danger',
            icon: ['fas', 'circle-exclamation'],
        };
    }
    return {
        wrap: 'bg-secondary',
        icon: ['fas', 'circle-info'],
    };
});

const role = computed(() => (variant.value === 'error' ? 'alert' : 'status'));
</script>

<template>
    <div
        v-if="visible"
        :class="
            cn(
                'fixed top-3 right-3 left-3 z-[60] flex w-auto max-w-none items-start gap-2.5 rounded-lg border border-line bg-ground px-3.5 py-3 text-charcoal shadow-toast sm:left-auto sm:w-full sm:max-w-sm',
            )
        "
        :role="role"
    >
        <div
            :class="
                cn(
                    'flex h-[22px] w-[22px] shrink-0 items-center justify-center rounded-full text-white',
                    toastMeta.wrap,
                )
            "
            aria-hidden="true"
        >
            <Icon
                :name="toastMeta.icon"
                size="xs"
            />
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
            class="shrink-0 cursor-pointer border-none bg-transparent p-0 text-muted hover:text-charcoal"
            :aria-label="$t('ui.toast.dismiss')"
            @click="dismiss"
        >
            <Icon
                :name="['fas', 'xmark']"
                size="sm"
            />
        </button>
    </div>
</template>
