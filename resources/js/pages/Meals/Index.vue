<script setup>
import AppLayout from '../../layouts/AppLayout.vue';
import { Button } from '../../components/ui/button';
import { EmptyState } from '../../components/ui/empty-state';
import { Icon } from '../../components/ui/icon';
import { computed } from 'vue';
import { trans } from 'laravel-vue-i18n';

const breadcrumbs = computed(() => [
    { label: trans('app.name'), href: '/dashboard' },
    { label: trans('nav.meals') },
]);
</script>

<template>
    <AppLayout
        :title="$t('nav.meals')"
        :breadcrumbs="breadcrumbs"
    >
        <div class="container mx-auto flex flex-col gap-5">
            <header class="flex flex-wrap items-center justify-between gap-4">
                <h1 class="m-0 text-2xl font-bold text-charcoal">
                    {{ $t('nav.meals') }}
                </h1>
                <Button
                    href="/meals/settings"
                    variant="outline"
                >
                    <Icon
                        :name="['fas', 'gear']"
                        size="sm"
                    />
                    {{ $t('meals.settings.title') }}
                </Button>
            </header>
            <EmptyState :description="$t('meals.empty')">
                <template #icon>
                    <Icon
                        :name="['fas', 'utensils']"
                        size="lg"
                    />
                </template>
            </EmptyState>
        </div>
    </AppLayout>
</template>
