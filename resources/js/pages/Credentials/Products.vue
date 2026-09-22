<script setup>
import AppLayout from '../../layouts/AppLayout.vue';
import { EmptyState } from '../../components/ui/empty-state';
import { computed } from 'vue';
import { trans } from 'laravel-vue-i18n';

const breadcrumbs = computed(() => [
    { label: trans('app.name'), href: '/dashboard' },
    { label: trans('nav.credentials'), href: '/credentials/passes' },
    { label: trans('nav.credentials.products') },
]);
</script>

<template>
    <AppLayout
        :title="$t('credentials.products.title')"
        :breadcrumbs="breadcrumbs"
    >
        <div class="container mx-auto">
            <EmptyState
                :title="$t('credentials.products.empty.title')"
                :description="$t('credentials.products.empty.description')"
            />
        </div>
    </AppLayout>
</template>
