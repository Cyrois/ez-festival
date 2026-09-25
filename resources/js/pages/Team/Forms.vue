<script setup>
import AppLayout from '../../layouts/AppLayout.vue';
import { Badge } from '../../components/ui/badge';
import { Button } from '../../components/ui/button';
import { EmptyState } from '../../components/ui/empty-state';
import { Icon } from '../../components/ui/icon';
import { Input } from '../../components/ui/input';
import { SegmentedControl } from '../../components/ui/segmented-control';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '../../components/ui/table';
import { useFlashToast } from '../../composables/useFlashToast';
import { router } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import { trans } from 'laravel-vue-i18n';

const props = defineProps({
    forms: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
    statuses: { type: Array, default: () => [] },
    event: { type: Object, default: null },
    canWrite: { type: Boolean, default: false },
});

const breadcrumbs = computed(() => [
    { label: trans('app.name'), href: '/dashboard' },
    { label: trans('team.title'), href: '/team/advancement' },
    { label: trans('nav.team.forms') },
]);
const search = ref(props.filters.search ?? '');
const status = ref(props.filters.status ?? '');
const { showSuccess } = useFlashToast();
let searchTimer;

const filterOptions = computed(() => [
    { value: '', label: trans('team.forms.filters.all') },
    ...props.statuses.map((value) => ({
        value,
        label: trans(`team.forms.status.${value}`),
    })),
]);

const applyFilters = () => {
    router.get(
        '/team/forms',
        {
            search: search.value || undefined,
            status: status.value || undefined,
        },
        { preserveState: true, preserveScroll: true, replace: true },
    );
};

watch(search, () => {
    window.clearTimeout(searchTimer);
    searchTimer = window.setTimeout(applyFilters, 250);
});
watch(status, applyFilters);

const copyLink = async (url) => {
    await navigator.clipboard.writeText(url);
    showSuccess(trans('team.forms.toast.link_copied'));
};
</script>

<template>
    <AppLayout
        :title="$t('team.forms.title')"
        :breadcrumbs="breadcrumbs"
    >
        <div class="container mx-auto flex flex-col gap-4">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <h1 class="m-0 text-2xl font-bold tracking-tight">
                        {{ $t('team.forms.title') }}
                    </h1>
                    <p class="mt-1 mb-0 text-sm text-muted">
                        {{ $t('team.forms.lead') }}
                    </p>
                </div>
                <Button
                    href="/team/forms/create"
                    :disabled="!canWrite"
                >
                    <Icon
                        :name="['fas', 'plus']"
                        size="sm"
                        class="mr-2"
                    />
                    {{ $t('team.forms.actions.create') }}
                </Button>
            </div>

            <div class="flex flex-wrap gap-3">
                <div class="relative min-w-64 flex-1 sm:max-w-sm">
                    <Icon
                        :name="['fas', 'magnifying-glass']"
                        size="sm"
                        class="pointer-events-none absolute top-1/2 left-3 -translate-y-1/2 text-muted"
                    />
                    <Input
                        v-model="search"
                        class="pl-9"
                        :placeholder="$t('team.forms.filters.search')"
                    />
                </div>
                <SegmentedControl
                    v-model="status"
                    :options="filterOptions"
                />
            </div>

            <Table v-if="forms.data.length">
                <TableHeader>
                    <TableRow variant="header">
                        <TableHead>{{ $t('team.forms.table.name') }}</TableHead>
                        <TableHead>{{
                            $t('team.forms.table.status')
                        }}</TableHead>
                        <TableHead>{{
                            $t('team.forms.table.public_link')
                        }}</TableHead>
                        <TableHead class="text-right">
                            {{ $t('team.forms.table.actions') }}
                        </TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableRow
                        v-for="teamForm in forms.data"
                        :key="teamForm.id"
                    >
                        <TableCell class="font-semibold">
                            {{ teamForm.name }}
                        </TableCell>
                        <TableCell>
                            <Badge
                                pill
                                :variant="
                                    teamForm.status === 'live'
                                        ? 'success'
                                        : 'warning'
                                "
                            >
                                {{ $t(`team.forms.status.${teamForm.status}`) }}
                            </Badge>
                        </TableCell>
                        <TableCell>
                            <button
                                v-if="teamForm.status === 'live'"
                                type="button"
                                class="max-w-72 truncate rounded-lg border border-line bg-page px-2 py-1 font-mono text-xs text-secondary hover:border-secondary"
                                :title="teamForm.public_url"
                                @click="copyLink(teamForm.public_url)"
                            >
                                {{ teamForm.public_url }}
                            </button>
                            <span
                                v-else
                                class="text-sm text-muted"
                            >
                                {{ $t('team.forms.link_after_live') }}
                            </span>
                        </TableCell>
                        <TableCell class="text-right">
                            <Button
                                :href="teamForm.edit_url"
                                variant="ghost"
                                size="sm"
                            >
                                {{ $t('team.forms.actions.edit') }}
                            </Button>
                        </TableCell>
                    </TableRow>
                </TableBody>
            </Table>

            <EmptyState
                v-else
                :title="$t('team.forms.empty.title')"
                :description="
                    $t(
                        search || status
                            ? 'team.forms.empty.filtered'
                            : 'team.forms.empty.description',
                    )
                "
            >
                <Button
                    v-if="!search && !status"
                    href="/team/forms/create"
                    :disabled="!canWrite"
                >
                    {{ $t('team.forms.actions.create') }}
                </Button>
            </EmptyState>
        </div>
    </AppLayout>
</template>
