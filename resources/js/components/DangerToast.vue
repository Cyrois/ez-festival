<script setup>
import { useFlashToast } from '../composables/useFlashToast';

const { message, visible, dismiss } = useFlashToast();
</script>

<template>
    <div
        v-if="visible"
        class="fixed top-3 right-3 z-50 flex max-w-sm items-start gap-2 rounded-lg bg-danger px-3 py-2.5 text-[11px] font-bold text-white shadow-lg"
        role="alert"
    >
        <span class="min-w-0 flex-1 leading-snug">{{ message }}</span>
        <button
            type="button"
            class="shrink-0 cursor-pointer border-none bg-transparent p-0 text-base leading-none text-white/80 hover:text-white"
            :aria-label="$t('setup.errors.dismiss')"
            @click="dismiss"
        >
            ×
        </button>
    </div>
</template>
