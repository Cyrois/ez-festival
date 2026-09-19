<script setup>
import AppLayout from '../../layouts/AppLayout.vue';
import { EmptyState } from '../../components/ui/empty-state';
import { Icon } from '../../components/ui/icon';
import { computed } from 'vue';
import { trans } from 'laravel-vue-i18n';

defineProps({
    event: { type: Object, required: true },
});

const breadcrumbs = computed(() => [
    { label: trans('app.name'), href: '/dashboard' },
    { label: trans('nav.credentials'), href: '/credentials/passes' },
    { label: trans('nav.credentials.entitlements') },
]);
</script>

<template>
    <AppLayout
        :title="$t('credentials.entitlements.title')"
        :breadcrumbs="breadcrumbs"
    >
        <div class="flex flex-col gap-6">
            <div>
                <h1 class="m-0 text-2xl font-bold tracking-tight">
                    {{ $t('credentials.entitlements.title') }}
                </h1>
                <p class="mt-1 mb-0 text-sm text-muted">
                    {{ $t('credentials.entitlements.lead') }}
                </p>
            </div>
            <EmptyState
                :title="$t('credentials.entitlements.empty.title')"
                :description="$t('credentials.entitlements.empty.description')"
            >
                <template #icon>
                    <Icon
                        :name="['fas', 'ticket']"
                        size="lg"
                    />
                </template>
            </EmptyState>
        </div>
    </AppLayout>
</template>
