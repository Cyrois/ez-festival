<script setup>
import { EmptyState } from '../../components/ui/empty-state';
import AppLayout from '../../layouts/AppLayout.vue';
import { computed } from 'vue';
import { trans } from 'laravel-vue-i18n';

const breadcrumbs = computed(() => [
    { label: trans('app.name'), href: '/dashboard' },
    { label: trans('team.title'), href: '/team/advancement' },
    { label: trans('nav.team.forms') },
]);
</script>

<template>
    <AppLayout
        :title="$t('team.forms.title')"
        :breadcrumbs="breadcrumbs"
    >
        <div class="container mx-auto">
            <EmptyState
                :title="$t('team.forms.empty.title')"
                :description="$t('team.forms.empty.description')"
            />
        </div>
    </AppLayout>
</template>
