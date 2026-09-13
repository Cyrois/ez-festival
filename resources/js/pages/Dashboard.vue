<script setup>
import AppLayout from '../layouts/AppLayout.vue';
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    event: {
        type: Object,
        default: null,
    },
});

const page = usePage();
const user = computed(() => page.props.auth?.user);
const eventName = computed(
    () => props.event?.name ?? page.props.activeEvent?.name ?? null,
);
</script>

<template>
    <AppLayout :title="$t('dashboard.title')">
        <h1 class="mb-2 text-2xl font-bold tracking-tight">
            {{ $t('dashboard.title') }}
        </h1>
        <p
            v-if="eventName"
            class="mb-2 text-sm font-semibold text-charcoal"
        >
            {{ $t('dashboard.event_name', { name: eventName }) }}
        </p>
        <p class="m-0 text-sm text-muted">
            {{
                $t('dashboard.signed_in_as', {
                    email: user?.email ?? $t('dashboard.unknown'),
                })
            }}
        </p>
    </AppLayout>
</template>
