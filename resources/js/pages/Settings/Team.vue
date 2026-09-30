<script setup>
import SettingsLayout from '../../layouts/SettingsLayout.vue';
import { Avatar } from '../../components/ui/avatar';
import { Badge } from '../../components/ui/badge';
import { Button } from '../../components/ui/button';
import { DataTable } from '../../components/ui/data-table';
import { EmptyState } from '../../components/ui/empty-state';
import { Icon } from '../../components/ui/icon';
import { Input } from '../../components/ui/input';
import { Link, router } from '@inertiajs/vue3';
import { computed, onUnmounted, ref, watch } from 'vue';
import { trans } from 'laravel-vue-i18n';
import { teamColumns } from './teamColumns';

defineProps({
    hasAnyPeople: { type: Boolean, required: true },
});

const breadcrumbs = computed(() => [
    { label: trans('app.name'), href: '/dashboard' },
    { label: trans('nav.settings'), href: '/settings/events' },
    { label: trans('settings.team.title') },
]);
const columns = computed(() => teamColumns(trans));
const table = ref(null);
const search = ref('');
let searchTimer;

const options = computed(() => ({
    serverSide: true,
    searching: true,
    lengthChange: false,
    pageLength: 25,
    order: [[0, 'asc']],
    layout: {
        topStart: null,
        topEnd: null,
    },
    columnDefs: [{ targets: 4, className: 'text-right' }],
    createdRow: (row, person) => {
        row.classList.add('cursor-pointer');
        row.addEventListener('click', (event) => {
            if (event.target.closest('a, button, input, select, textarea')) {
                return;
            }

            router.get(`/settings/team/${person.id}`);
        });
    },
    language: {
        emptyTable: trans('settings.team.empty.description'),
        zeroRecords: trans('settings.team.no_matches'),
    },
}));

watch(search, (value) => {
    window.clearTimeout(searchTimer);
    searchTimer = window.setTimeout(() => table.value?.search(value), 300);
});

onUnmounted(() => window.clearTimeout(searchTimer));
</script>

<template>
    <SettingsLayout
        :title="$t('settings.team.title')"
        :breadcrumbs="breadcrumbs"
    >
        <div class="container mx-auto">
            <div
                class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between"
            >
                <div>
                    <h1 class="m-0 text-2xl font-bold tracking-tight">
                        {{ $t('settings.team.title') }}
                    </h1>
                    <p class="mt-1 mb-0 max-w-2xl text-sm text-muted">
                        {{ $t('settings.team.lead') }}
                    </p>
                </div>
                <Button
                    href="/settings/team/create"
                    class="min-h-11 w-full sm:w-auto"
                >
                    <Icon
                        :name="['fas', 'plus']"
                        size="sm"
                    />
                    {{ $t('settings.team.actions.add') }}
                </Button>
            </div>

            <EmptyState
                v-if="!hasAnyPeople"
                :title="$t('settings.team.empty.title')"
                :description="$t('settings.team.empty.description')"
            >
                <template #icon>
                    <Icon
                        :name="['fas', 'users']"
                        size="lg"
                    />
                </template>
                <Button href="/settings/team/create">
                    {{ $t('settings.team.actions.add') }}
                </Button>
            </EmptyState>

            <template v-else>
                <form
                    class="relative mb-3 max-w-sm"
                    role="search"
                    @submit.prevent
                >
                    <Icon
                        :name="['fas', 'magnifying-glass']"
                        class="pointer-events-none absolute top-3.5 left-3 z-10 text-muted"
                        size="sm"
                    />
                    <Input
                        v-model="search"
                        type="search"
                        class="min-h-11 pl-9"
                        :placeholder="$t('settings.team.search')"
                        :aria-label="$t('settings.team.search')"
                        maxlength="255"
                    />
                </form>

                <DataTable
                    ref="table"
                    ajax="/settings/team/data"
                    :columns="columns"
                    :options="options"
                >
                    <template #personCell="{ rowData }">
                        <Link
                            :href="`/settings/team/${rowData.id}`"
                            class="flex items-center gap-3 font-semibold text-charcoal no-underline hover:text-primary hover:underline"
                        >
                            <Avatar
                                :name="rowData.name"
                                size="sm"
                            />
                            {{ rowData.name }}
                        </Link>
                    </template>
                    <template #loginCell="{ cellData }">
                        <Badge
                            v-if="cellData"
                            variant="primary"
                            class="gap-1.5"
                            pill
                        >
                            <Icon
                                :name="['fas', 'key']"
                                size="sm"
                            />
                            {{ $t('settings.team.login.enabled') }}
                        </Badge>
                        <span
                            v-else
                            class="text-sm text-muted"
                        >
                            {{ $t('settings.team.login.disabled') }}
                        </span>
                    </template>
                    <template #eventsCell="{ rowData }">
                        <Badge
                            v-if="rowData.is_admin"
                            variant="orange"
                            pill
                        >
                            {{ $t('settings.team.access.admin_badge') }}
                        </Badge>
                        <div
                            v-else
                            class="flex flex-wrap gap-1.5"
                        >
                            <Badge
                                v-for="access in rowData.events"
                                :key="access.event_id"
                                :variant="
                                    access.role_active ? 'primary' : 'neutral'
                                "
                                :class="!access.role_active && 'text-muted'"
                                pill
                            >
                                {{ access.event_name }} · {{ access.role_name
                                }}<template v-if="!access.role_active">
                                    {{
                                        $t('settings.team.role_off_suffix')
                                    }}</template
                                >
                            </Badge>
                        </div>
                    </template>
                    <template #openCell="{ rowData }">
                        <Link
                            :href="`/settings/team/${rowData.id}`"
                            class="inline-flex text-muted hover:text-primary"
                            :aria-label="$t('settings.team.columns.open')"
                        >
                            <Icon
                                :name="['fas', 'chevron-right']"
                                size="sm"
                            />
                        </Link>
                    </template>
                </DataTable>
            </template>
        </div>
    </SettingsLayout>
</template>
