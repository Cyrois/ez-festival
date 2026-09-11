<script setup>
import { Head, Link } from '@inertiajs/vue3';
</script>

<template>
    <Head :title="$t('auth.forgot.title')" />

    <div
        class="flex min-h-screen items-center justify-center bg-[#F4F5F7] p-6 text-[#1A1A1A] antialiased"
    >
        <div class="w-full max-w-[400px]">
            <div class="mb-7 text-center">
                <div
                    class="mb-3 inline-flex h-11 w-11 items-center justify-center rounded-[10px] bg-[#1F7A74] text-[15px] font-bold text-white"
                    aria-hidden="true"
                >
                    {{ $t('app.mark') }}
                </div>
                <h1 class="m-0 text-xl font-bold tracking-tight">{{ $t('app.name') }}</h1>
            </div>

            <div class="rounded-xl border border-[#E5E7EB] bg-white px-7 pb-7 pt-8">
                <h2 class="mb-1.5 text-[22px] font-bold tracking-tight">{{ $t('auth.forgot.title') }}</h2>
                <p class="mb-6 text-sm leading-snug text-[#6B7280]">
                    {{ $t('auth.forgot.body') }}
                </p>
                <Link href="/login" class="text-sm font-bold text-[#3D6B8A] no-underline hover:underline">
                    {{ $t('auth.forgot.back') }}
                </Link>
            </div>
        </div>
    </div>
</template>
