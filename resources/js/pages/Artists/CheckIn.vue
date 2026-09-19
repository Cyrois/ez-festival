<script setup>
import AppLayout from '../../layouts/AppLayout.vue';
import { EmptyState } from '../../components/ui/empty-state';
import { Icon } from '../../components/ui/icon';
import { computed } from 'vue';
import { trans } from 'laravel-vue-i18n';

const breadcrumbs = computed(() => [
    { label: trans('app.name'), href: '/dashboard' },
    { label: trans('nav.artists'), href: '/artists/advancing' },
    { label: trans('artists.check_in.title') },
]);
</script>

<template>
    <AppLayout
        :title="$t('artists.check_in.title')"
        :breadcrumbs="breadcrumbs"
    >
        <div class="flex flex-col gap-6">
            <div>
                <h1 class="m-0 text-2xl font-bold tracking-tight">
                    {{ $t('artists.check_in.title') }}
                </h1>
                <p class="mt-1 mb-0 text-sm text-muted">
                    {{ $t('artists.check_in.lead') }}
                </p>
            </div>

            <EmptyState
                :title="$t('artists.check_in.empty.title')"
                :description="$t('artists.check_in.empty.description')"
            >
                <template #icon>
                    <Icon
                        :name="['fas', 'clipboard-check']"
                        size="lg"
                    />
                </template>
            </EmptyState>
        </div>
    </AppLayout>
</template>
