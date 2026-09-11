<script setup>
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const page = usePage();
const user = computed(() => page.props.auth?.user);
</script>

<template>
    <Head :title="$t('dashboard.title')" />

    <div
        class="min-h-screen bg-[#F4F5F7] text-[#1A1A1A]"
    >
        <header class="flex items-center justify-between border-b border-[#E5E7EB] bg-white px-6 py-4">
            <div class="flex items-center gap-2.5">
                <div
                    class="inline-flex h-8 w-8 items-center justify-center rounded-lg bg-[#1F7A74] text-xs font-bold text-white"
                    aria-hidden="true"
                >
                    {{ $t('app.mark') }}
                </div>
                <span class="text-[15px] font-bold">{{ $t('app.name') }}</span>
            </div>
            <Link
                method="post"
                href="/logout"
                as="button"
                class="cursor-pointer rounded-lg border border-[#E5E7EB] bg-white px-3 py-2 text-[13px] font-semibold text-[#1A1A1A]"
            >
                {{ $t('dashboard.sign_out') }}
            </Link>
        </header>

        <main class="mx-auto max-w-[720px] px-6 py-10">
            <h1 class="mb-2 text-2xl font-bold tracking-tight">{{ $t('dashboard.title') }}</h1>
            <p class="m-0 text-sm text-[#6B7280]">
                {{ $t('dashboard.signed_in_as', { email: user?.email ?? $t('dashboard.unknown') }) }}
            </p>
        </main>
    </div>
</template>
