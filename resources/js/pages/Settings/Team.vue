<script setup>
import SettingsLayout from '../../layouts/SettingsLayout.vue';
import { EmptyState } from '../../components/ui/empty-state';
import { Icon } from '../../components/ui/icon';
import { computed } from 'vue';
import { trans } from 'laravel-vue-i18n';

const breadcrumbs = computed(() => [
    { label: trans('app.name'), href: '/dashboard' },
    { label: trans('nav.settings'), href: '/settings/events' },
    { label: trans('settings.team.title') },
]);
</script>

<template>
    <SettingsLayout
        :title="$t('settings.team.title')"
        :breadcrumbs="breadcrumbs"
    >
        <div class="flex flex-col gap-6">
            <div>
                <h1 class="m-0 text-2xl font-bold tracking-tight">
                    {{ $t('settings.team.title') }}
                </h1>
                <p class="mt-1 mb-0 text-sm text-muted">
                    {{ $t('settings.team.lead') }}
                </p>
            </div>

            <EmptyState
                :title="$t('settings.team.empty.title')"
                :description="$t('settings.team.empty.description')"
            >
                <template #icon>
                    <Icon
                        :name="['fas', 'address-book']"
                        size="lg"
                    />
                </template>
            </EmptyState>
        </div>
    </SettingsLayout>
</template>
