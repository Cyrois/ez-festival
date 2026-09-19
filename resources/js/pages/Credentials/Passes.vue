<script setup>
import AppLayout from '../../layouts/AppLayout.vue';
import PassRow from '../../components/credentials/PassRow.vue';
import { Button } from '../../components/ui/button';
import { Icon } from '../../components/ui/icon';
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { trans } from 'laravel-vue-i18n';

const page = usePage();
const eventName = computed(() => page.props.activeEvent?.name ?? '');
const breadcrumbs = computed(() => [
    { label: trans('app.name'), href: '/dashboard' },
    { label: trans('nav.credentials'), href: '/credentials/passes' },
    { label: trans('credentials.passes.title') },
]);
const lead = computed(() =>
    trans('credentials.passes.lead', { event: eventName.value }),
);
const samplePasses = computed(() => [
    {
        name: trans('credentials.passes.samples.artist'),
        usage: trans('credentials.passes.usage.limited', {
            assigned: 12,
            capacity: 50,
        }),
    },
    {
        name: trans('credentials.passes.samples.guest'),
        usage: trans('credentials.passes.usage.unlimited', { assigned: 8 }),
    },
    {
        name: trans('credentials.passes.samples.vendor_staff'),
        usage: trans('credentials.passes.usage.limited', {
            assigned: 24,
            capacity: 40,
        }),
    },
    {
        name: trans('credentials.passes.samples.vip'),
        usage: trans('credentials.passes.usage.unlimited', { assigned: 3 }),
    },
]);
</script>

<template>
    <AppLayout
        :title="$t('credentials.passes.title')"
        :breadcrumbs="breadcrumbs"
    >
        <div class="mx-auto w-full max-w-[824px]">
            <div class="mb-3 flex flex-wrap items-start justify-between gap-4">
                <div>
                    <h1 class="m-0 text-2xl font-bold tracking-tight">
                        {{ $t('credentials.passes.title') }}
                    </h1>
                    <p class="mt-1 mb-0 text-sm text-muted">
                        {{ lead }}
                    </p>
                </div>
                <Button
                    variant="primary"
                    class="min-h-10"
                >
                    <Icon
                        :name="['fas', 'plus']"
                        size="sm"
                    />
                    {{ $t('credentials.passes.create') }}
                </Button>
            </div>

            <div class="space-y-2.5">
                <PassRow
                    v-for="pass in samplePasses"
                    :key="pass.name"
                    :name="pass.name"
                    :usage="pass.usage"
                />
            </div>

            <p class="mt-3 mb-0 text-xs leading-5 text-muted">
                {{ $t('credentials.passes.note') }}
            </p>
        </div>
    </AppLayout>
</template>
