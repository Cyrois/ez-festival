<script setup>
import { EmptyState } from '../../components/ui/empty-state';
import AppLayout from '../../layouts/AppLayout.vue';
import { computed } from 'vue';
import { trans } from 'laravel-vue-i18n';

const breadcrumbs = computed(() => [
    { label: trans('app.name'), href: '/dashboard' },
    { label: trans('team.title'), href: '/team/advancement' },
    { label: trans('nav.team.scheduling') },
]);
</script>

<template>
    <AppLayout
        :title="$t('team.scheduling.title')"
        :breadcrumbs="breadcrumbs"
    >
        <div class="container mx-auto">
            <EmptyState
                :title="$t('team.scheduling.empty.title')"
                :description="$t('team.scheduling.empty.description')"
            />
        </div>
    </AppLayout>
</template>
