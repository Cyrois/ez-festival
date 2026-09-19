<script setup>
import AppLayout from '../../layouts/AppLayout.vue';
import { Button } from '../../components/ui/button';
import { Card } from '../../components/ui/card';
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
                    disabled
                    class="min-h-10 disabled:opacity-100"
                    :title="$t('credentials.passes.stub_action')"
                >
                    <Icon
                        :name="['fas', 'plus']"
                        size="sm"
                    />
                    {{ $t('credentials.passes.create') }}
                </Button>
            </div>

            <div class="space-y-2.5">
                <Card
                    v-for="pass in samplePasses"
                    :key="pass.name"
                    class="flex min-h-[60px] items-center justify-between gap-4 rounded-xl px-3.5 py-2.5"
                >
                    <div class="min-w-0">
                        <h2 class="m-0 truncate text-sm font-semibold">
                            {{ pass.name }}
                        </h2>
                        <p class="mt-0.5 mb-0 text-xs text-muted">
                            {{ pass.usage }}
                        </p>
                    </div>
                    <div class="flex shrink-0 items-center gap-2">
                        <Button
                            variant="outline"
                            size="icon"
                            disabled
                            class="h-8 w-8 cursor-default disabled:opacity-100"
                            :aria-label="
                                $t('credentials.passes.edit', {
                                    pass: pass.name,
                                })
                            "
                            :title="$t('credentials.passes.stub_action')"
                        >
                            <Icon
                                :name="['fas', 'pen']"
                                size="sm"
                            />
                        </Button>
                        <Button
                            variant="outline"
                            size="icon"
                            disabled
                            class="h-8 w-8 cursor-default disabled:opacity-100"
                            :aria-label="
                                $t('credentials.passes.delete', {
                                    pass: pass.name,
                                })
                            "
                            :title="$t('credentials.passes.stub_action')"
                        >
                            <Icon
                                :name="['fas', 'trash-can']"
                                size="sm"
                            />
                        </Button>
                    </div>
                </Card>
            </div>

            <p class="mt-3 mb-0 text-xs leading-5 text-muted">
                {{ $t('credentials.passes.note') }}
            </p>
        </div>
    </AppLayout>
</template>
