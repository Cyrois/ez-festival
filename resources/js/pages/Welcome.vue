<script setup>
</script>

<template>
    <div class="flex min-h-screen items-center justify-center bg-slate-50 p-6 text-slate-900">
        <div class="w-full max-w-lg rounded-2xl border border-slate-200 bg-white p-8 shadow-sm">
            <p class="text-sm font-medium uppercase tracking-wide text-indigo-600">{{ $t('welcome.badge') }}</p>
            <h1 class="mt-2 text-3xl font-semibold tracking-tight">{{ $t('app.name') }}</h1>
            <p class="mt-4 text-slate-600">
                {{ $t('welcome.body') }}
            </p>
        </div>
    </div>
</template>
