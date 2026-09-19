<script setup>
import AppLayout from '../../layouts/AppLayout.vue';
import { EmptyState } from '../../components/ui/empty-state';
import { Icon } from '../../components/ui/icon';
import { computed } from 'vue';
import { trans } from 'laravel-vue-i18n';

const breadcrumbs = computed(() => [
    { label: trans('app.name'), href: '/dashboard' },
    { label: trans('patrons.title') },
]);
</script>

<template>
    <AppLayout
        :title="$t('patrons.title')"
        :breadcrumbs="breadcrumbs"
    >
        <div class="flex flex-col gap-6">
            <div>
                <h1 class="m-0 text-2xl font-bold tracking-tight">
                    {{ $t('patrons.title') }}
                </h1>
                <p class="mt-1 mb-0 text-sm text-muted">
                    {{ $t('patrons.lead') }}
                </p>
            </div>

            <EmptyState
                :title="$t('patrons.empty.title')"
                :description="$t('patrons.empty.description')"
            >
                <template #icon>
                    <Icon
                        :name="['fas', 'address-book']"
                        size="lg"
                    />
                </template>
            </EmptyState>
        </div>
    </AppLayout>
</template>
