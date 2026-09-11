<script setup>
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    title: { type: String, required: true },
    crumb: { type: String, required: true },
    currentStep: { type: Number, required: true },
    organizationName: { type: String, default: '' },
    eventName: { type: String, default: '' },
});

const page = usePage();
const subtitle = computed(
    () => props.eventName || props.organizationName || page.props.organization?.name || '',
);

const steps = [
    { n: 1, key: 'event', route: '/setup/event' },
    { n: 2, key: 'locations', route: '/setup/locations' },
    { n: 3, key: 'vendor_types', route: '/setup/vendor-types' },
    { n: 4, key: 'artist_types', route: '/setup/artist-types' },
];

const stepClass = (n) => {
    if (n === props.currentStep) {
        return 'bg-brand text-white border-brand';
    }
    if (n < props.currentStep) {
        return 'bg-brand/10 text-brand border-transparent';
    }
    return 'bg-white text-muted border-line';
};
</script>

<template>
    <Head :title="title" />

    <div class="flex min-h-screen bg-page text-charcoal antialiased">
        <aside class="flex w-[200px] shrink-0 flex-col border-r border-line bg-white px-2 py-3">
            <div class="mb-3 flex items-center gap-2 px-1.5 py-1">
                <div
                    class="inline-flex h-8 w-8 items-center justify-center rounded-lg bg-brand text-[10px] font-bold text-white"
                    aria-hidden="true"
                >
                    {{ $t('app.mark') }}
                </div>
                <div class="min-w-0">
                    <strong class="block truncate text-[11px] font-bold">{{ $t('app.name') }}</strong>
                    <span class="block truncate text-[9px] text-muted">{{ subtitle }}</span>
                </div>
            </div>
            <nav class="flex flex-col gap-0.5">
                <span
                    class="flex items-center gap-2 rounded-lg bg-brand/10 px-2 py-1.5 text-[11px] font-bold text-brand"
                >
                    <svg class="h-3.5 w-3.5 shrink-0" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                        <path
                            fill-rule="evenodd"
                            d="M11.49 3.17c-.38-1.56-2.6-1.56-2.98 0a1.532 1.532 0 01-2.286.948c-1.372-.836-2.942.734-2.106 2.106.54.886.061 2.042-.947 2.287-1.561.379-1.561 2.6 0 2.978a1.532 1.532 0 01.947 2.287c-.836 1.372.734 2.942 2.106 2.106a1.532 1.532 0 012.287.947c.379 1.561 2.6 1.561 2.978 0a1.533 1.533 0 012.287-.947c1.372.836 2.942-.734 2.106-2.106a1.533 1.533 0 01.947-2.287c1.561-.379 1.561-2.6 0-2.978a1.532 1.532 0 01-.947-2.287c.836-1.372-.734-2.942-2.106-2.106a1.532 1.532 0 01-2.287-.947zM10 13a3 3 0 100-6 3 3 0 000 6z"
                            clip-rule="evenodd"
                        />
                    </svg>
                    {{ $t('nav.setup') }}
                </span>
            </nav>
        </aside>

        <div class="flex min-w-0 flex-1 flex-col">
            <div class="flex h-9 items-center border-b border-line px-4 text-[10px] text-muted">
                {{ crumb }}
            </div>
            <main class="flex-1 px-4 py-3">
                <div v-if="currentStep <= 4" class="mb-3 flex flex-wrap gap-1.5">
                    <Link
                        v-for="step in steps"
                        :key="step.n"
                        :href="step.route"
                        class="rounded-full border px-2 py-1 text-[10px] font-bold no-underline"
                        :class="stepClass(step.n)"
                    >
                        {{ $t(`setup.steps.${step.key}`) }}
                    </Link>
                </div>
                <slot />
            </main>
        </div>
    </div>
</template>
