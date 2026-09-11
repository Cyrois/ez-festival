<script setup>
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    organization: { type: Object, default: null },
    event: { type: Object, default: null },
});

const page = usePage();
const user = computed(() => page.props.auth?.user);
const eventName = computed(() => props.event?.name ?? page.props.activeEvent?.name ?? null);
</script>

<template>
    <Head :title="$t('dashboard.title')" />

    <div class="min-h-screen bg-page text-charcoal">
        <header class="flex items-center justify-between border-b border-line bg-white px-6 py-4">
            <div class="flex items-center gap-2.5">
                <div
                    class="inline-flex h-8 w-8 items-center justify-center rounded-lg bg-brand text-xs font-bold text-white"
                    aria-hidden="true"
                >
                    {{ $t('app.mark') }}
                </div>
                <div>
                    <span class="block text-[15px] font-bold">{{ $t('app.name') }}</span>
                    <span v-if="eventName" class="block text-xs text-muted">{{ eventName }}</span>
                </div>
            </div>
            <div class="flex items-center gap-2">
                <Link
                    href="/setup/event"
                    class="cursor-pointer rounded-lg border border-line bg-white px-3 py-2 text-[13px] font-semibold text-charcoal no-underline"
                >
                    {{ $t('nav.setup') }}
                </Link>
                <Link
                    method="post"
                    href="/logout"
                    as="button"
                    class="cursor-pointer rounded-lg border border-line bg-white px-3 py-2 text-[13px] font-semibold text-charcoal"
                >
                    {{ $t('dashboard.sign_out') }}
                </Link>
            </div>
        </header>

        <main class="mx-auto max-w-[720px] px-6 py-10">
            <h1 class="mb-2 text-2xl font-bold tracking-tight">{{ $t('dashboard.title') }}</h1>
            <p v-if="eventName" class="mb-2 text-sm font-semibold text-charcoal">
                {{ $t('dashboard.event_name', { name: eventName }) }}
            </p>
            <p class="m-0 text-sm text-muted">
                {{ $t('dashboard.signed_in_as', { email: user?.email ?? $t('dashboard.unknown') }) }}
            </p>
        </main>
    </div>
</template>
