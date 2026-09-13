<script setup>
import SetupLayout from '../../layouts/SetupLayout.vue';
import { Button } from '../../components/ui/button';
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
        :current-step="currentStep"
        :organization-name="organization.name"
    >
        <div
            class="mb-5 rounded-xl border border-success/40 bg-success/15 px-4 py-3 text-sm leading-snug text-charcoal"
        >
            {{ $t('setup.ready.callout') }}
        </div>

        <div class="mb-5">
            <h1 class="m-0 mb-1.5 text-[28px] font-bold tracking-tight">
                {{ $t('setup.ready.heading') }}
            </h1>
            <p class="m-0 text-sm leading-snug text-muted">
                {{ $t('setup.ready.lead') }}
            </p>
            <p
                v-if="event"
                class="mt-3 text-sm font-bold text-charcoal"
            >
                {{ $t('setup.ready.event_label', { name: event.name }) }}
            </p>
        </div>

        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
            <div
                class="overflow-hidden rounded-xl border border-line bg-ground"
            >
                <div
                    class="border-b border-line bg-page px-4 py-3 text-sm font-bold"
                >
                    {{ $t('setup.ready.vendor_types_label') }}
                </div>
                <div class="px-4 py-3 text-sm">
                    {{ vendorSummary }}
                </div>
            </div>
            <div
                class="overflow-hidden rounded-xl border border-line bg-ground"
            >
                <div
                    class="border-b border-line bg-page px-4 py-3 text-sm font-bold"
                >
                    {{ $t('setup.ready.artist_types_label') }}
                </div>
                <div class="px-4 py-3 text-sm">
                    {{ artistSummary }}
                </div>
            </div>
        </div>

        <p class="mt-4 text-xs leading-snug text-muted">
            {{ $t('setup.ready.note') }}
        </p>

        <div class="mt-5 flex justify-end">
            <Button
                type="button"
                variant="primary"
                @click="enterApp"
            >
                {{ $t('setup.actions.enter_app') }}
            </Button>
        </div>
    </SetupLayout>
</template>
