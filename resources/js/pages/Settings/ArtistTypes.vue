<script setup>
import SettingsLayout from '../../layouts/SettingsLayout.vue';
import TypeManager from '../../components/settings/TypeManager.vue';
import { computed } from 'vue';
import { trans } from 'laravel-vue-i18n';

defineProps({
    types: { type: Array, default: () => [] },
});

const breadcrumbs = computed(() => [
    { label: trans('app.name'), href: '/dashboard' },
    { label: trans('nav.settings'), href: '/settings/events' },
    { label: trans('settings.artist_types.title') },
]);
</script>

<template>
    <SettingsLayout
        :title="$t('settings.artist_types.title')"
        :breadcrumbs="breadcrumbs"
    >
        <TypeManager
            :types="types"
            endpoint="/settings/artist-types"
            empty-key="settings.artist_types.empty"
        >
            <template #header>
                <div>
                    <h1 class="m-0 text-2xl font-bold tracking-tight">
                        {{ $t('settings.artist_types.title') }}
                    </h1>
                    <p class="mt-1 mb-0 text-sm text-muted">
                        {{ $t('settings.artist_types.lead') }}
                    </p>
                </div>
            </template>
        </TypeManager>
    </SettingsLayout>
</template>
