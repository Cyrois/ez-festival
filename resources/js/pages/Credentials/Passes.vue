<script setup>
import AppLayout from '../../layouts/AppLayout.vue';
import PassRow from '../../components/credentials/PassRow.vue';
import { Button } from '../../components/ui/button';
import { EmptyState } from '../../components/ui/empty-state';
import { Icon } from '../../components/ui/icon';
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { trans } from 'laravel-vue-i18n';

const props = defineProps({
    passes: { type: Array, default: () => [] },
    canWrite: { type: Boolean, default: false },
});

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

const usageFor = (pass) =>
    pass.max_assignments === null
        ? trans('credentials.passes.usage.unlimited', {
              assigned: pass.assigned_count,
          })
        : trans('credentials.passes.usage.limited', {
              assigned: pass.assigned_count,
              capacity: pass.max_assignments,
          });
</script>

<template>
    <AppLayout
        :title="$t('credentials.passes.title')"
        :breadcrumbs="breadcrumbs"
    >
        <div class="w-full">
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
                    v-if="canWrite"
                    href="/credentials/passes/create"
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
                    v-for="pass in props.passes"
                    :key="pass.id"
                    :name="pass.name"
                    :usage="usageFor(pass)"
                />
            </div>

            <EmptyState
                v-if="props.passes.length === 0"
                class="mt-5"
                :title="$t('credentials.passes.empty.title')"
                :description="$t('credentials.passes.empty.description')"
            >
                <template #icon>
                    <Icon
                        :name="['fas', 'id-card']"
                        size="lg"
                    />
                </template>
                <Button
                    v-if="canWrite"
                    href="/credentials/passes/create"
                    variant="outline"
                >
                    <Icon
                        :name="['fas', 'plus']"
                        size="sm"
                    />
                    {{ $t('credentials.passes.create') }}
                </Button>
            </EmptyState>

            <p
                v-if="!canWrite"
                class="mt-3 mb-0 text-xs leading-5 text-muted"
            >
                {{ $t('credentials.passes.read_only') }}
            </p>

            <p class="mt-3 mb-0 text-xs leading-5 text-muted">
                {{ $t('credentials.passes.note') }}
            </p>
        </div>
    </AppLayout>
</template>
