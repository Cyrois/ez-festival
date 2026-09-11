<script setup>
import SetupLayout from '../../layouts/SetupLayout.vue';
import { router } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    organization: { type: Object, required: true },
    event: { type: Object, default: null },
    vendorTypes: { type: Array, required: true },
    artistTypes: { type: Array, required: true },
    currentStep: { type: Number, required: true },
});

const vendorSummary = computed(() =>
    props.vendorTypes.length ? props.vendorTypes.join(' · ') : '—',
);
const artistSummary = computed(() =>
    props.artistTypes.length ? props.artistTypes.join(' · ') : '—',
);

const enterApp = () => router.post('/setup/ready');
</script>

<template>
    <SetupLayout
        :title="$t('setup.ready.title')"
        :crumb="$t('setup.crumbs.ready')"
        :current-step="currentStep"
        :organization-name="organization.name"
        :event-name="event?.name"
    >
        <div
            class="mb-2.5 rounded-lg border border-success/40 bg-success/15 px-2.5 py-2 text-[11px] leading-snug text-charcoal"
        >
            {{ $t('setup.ready.callout') }}
        </div>

        <div class="mb-3">
            <h2 class="m-0 mb-1 text-base font-bold">{{ $t('setup.ready.heading') }}</h2>
            <p class="m-0 text-[11px] leading-snug text-muted">{{ $t('setup.ready.lead') }}</p>
            <p v-if="event" class="mt-2 text-[12px] font-bold text-charcoal">
                {{ $t('setup.ready.event_label', { name: event.name }) }}
            </p>
        </div>

        <div class="mt-2 grid grid-cols-2 gap-2">
            <div class="overflow-hidden rounded-lg border border-line">
                <div class="border-b border-line bg-page px-2.5 py-2 text-[11px] font-bold">
                    {{ $t('setup.ready.vendor_types_label') }}
                </div>
                <div class="px-2.5 py-2 text-[11px]">{{ vendorSummary }}</div>
            </div>
            <div class="overflow-hidden rounded-lg border border-line">
                <div class="border-b border-line bg-page px-2.5 py-2 text-[11px] font-bold">
                    {{ $t('setup.ready.artist_types_label') }}
                </div>
                <div class="px-2.5 py-2 text-[11px]">{{ artistSummary }}</div>
            </div>
        </div>

        <p class="mt-2 text-[10px] leading-snug text-muted">{{ $t('setup.ready.note') }}</p>

        <div class="mt-3 flex justify-end">
            <button
                type="button"
                class="h-7 cursor-pointer rounded-md border-none bg-brand px-2.5 text-[11px] font-bold text-white hover:bg-brand-hover"
                @click="enterApp"
            >
                {{ $t('setup.actions.enter_app') }}
            </button>
        </div>
    </SetupLayout>
</template>
