<script setup>
import { computed } from 'vue';

const appName = computed(() => import.meta.env.VITE_APP_NAME || 'Laravel');
</script>

<template>
    <div class="min-h-screen bg-slate-50 text-slate-900 flex items-center justify-center p-6">
        <div class="max-w-lg w-full rounded-2xl border border-slate-200 bg-white p-8 shadow-sm">
            <p class="text-sm font-medium uppercase tracking-wide text-indigo-600">Vue 3 + Inertia</p>
            <h1 class="mt-2 text-3xl font-semibold tracking-tight">{{ appName }}</h1>
            <p class="mt-4 text-slate-600">
                Welcome — Vue 3 and Inertia are wired up and working.
            </p>
        </div>
    </div>
</template>
