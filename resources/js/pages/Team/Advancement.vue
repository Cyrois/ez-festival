<script setup>
import AdvancementBoardCard from '../../components/advancement/AdvancementBoardCard.vue';
import AdvancementViewToggle from '../../components/advancement/AdvancementViewToggle.vue';
import AppLayout from '../../layouts/AppLayout.vue';
import { Avatar } from '../../components/ui/avatar';
import { Badge } from '../../components/ui/badge';
import { Board } from '../../components/ui/board';
import { Button } from '../../components/ui/button';
import { EmptyState } from '../../components/ui/empty-state';
import { Icon } from '../../components/ui/icon';
import { Input } from '../../components/ui/input';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '../../components/ui/table';
import { useAdvancementBoard } from '../../composables/useAdvancementBoard';
import { engagementStatusPresentation } from '../../lib/engagementStatusPresentation';
import { Link } from '@inertiajs/vue3';
import { computed, onUnmounted, ref, watch } from 'vue';
import { trans } from 'laravel-vue-i18n';

const props = defineProps({
    engagements: { type: Object, required: true },
    statuses: { type: Array, required: true },
    employmentTypes: { type: Array, required: true },
    statusCounts: { type: Object, required: true },
    filters: { type: Object, required: true },
    event: { type: Object, default: null },
    canWrite: { type: Boolean, required: true },
});

const search = ref(props.filters.search);
const selectedEmploymentTypes = ref([...props.filters.employment_types]);
const viewMode = ref(props.filters.view);
let searchTimer;

const breadcrumbs = computed(() => [
    { label: trans('app.name'), href: '/dashboard' },
    { label: trans('team.title'), href: '/team/advancement' },
    { label: trans('nav.team.advancement') },
]);
const boardColumns = computed(() =>
    props.statuses.map((status) => ({
        value: status,
        label: trans(`team.advancement.status.${status}`),
        ...engagementStatusPresentation[status],
    })),
);
const employmentTypePresentation = {
    volunteer: 'border-secondary/20 bg-secondary/10 text-secondary',
    paid: 'border-success/20 bg-success/10 text-success',
};
const {
    applyFilters: applyBoardFilters,
    busy,
    engagementItems,
    localStatusCounts,
    moveEngagement,
    movingIds,
} = useAdvancementBoard({
    indexUrl: '/team/advancement',
    statusUrl: (item) => `/team/members/${item.id}/status`,
    items: () => props.engagements.data,
    serverCounts: () => props.statusCounts,
    filterPayload: () => ({
        search: search.value,
        employment_types: selectedEmploymentTypes.value,
        view: viewMode.value,
    }),
    canMove: () => props.canWrite,
});
const applyFilters = () => {
    window.clearTimeout(searchTimer);
    applyBoardFilters();
};

const toggleEmploymentType = (type) => {
    selectedEmploymentTypes.value = selectedEmploymentTypes.value.includes(type)
        ? selectedEmploymentTypes.value.filter((value) => value !== type)
        : [...selectedEmploymentTypes.value, type];
    applyFilters();
};
const clearFilters = () => {
    search.value = '';
    selectedEmploymentTypes.value = [];
    applyFilters();
};
const updateViewMode = (value) => {
    viewMode.value = value;
    applyFilters();
};
watch(search, () => {
    window.clearTimeout(searchTimer);
    searchTimer = window.setTimeout(applyFilters, 300);
});
watch(
    () => props.filters,
    (filters) => {
        search.value = filters.search;
        selectedEmploymentTypes.value = [...filters.employment_types];
        viewMode.value = filters.view;
    },
);
onUnmounted(() => window.clearTimeout(searchTimer));
</script>

<template>
    <AppLayout
        :title="$t('team.advancement.title')"
        :breadcrumbs="breadcrumbs"
    >
        <div class="container mx-auto">
            <header
                class="mb-5 flex flex-wrap items-start justify-between gap-4"
            >
                <div>
                    <h1 class="m-0 text-2xl font-bold tracking-tight">
                        {{ $t('team.advancement.title') }}
                    </h1>
                    <p class="mt-1 mb-0 text-sm text-muted">
                        {{ $t('team.advancement.lead') }}
                    </p>
                </div>
                <Button
                    v-if="event && canWrite"
                    href="/team/members/create"
                    class="min-h-11 w-full sm:w-auto"
                >
                    <Icon
                        :name="['fas', 'plus']"
                        size="sm"
                    />
                    {{ $t('team.advancement.add') }}
                </Button>
            </header>

            <EmptyState
                v-if="!event"
                :title="$t('team.advancement.no_event.title')"
                :description="$t('team.advancement.no_event.body')"
            >
                <Button href="/settings/events">
                    {{ $t('settings.events.title') }}
                </Button>
            </EmptyState>

            <template v-else>
                <p
                    v-if="event.locked"
                    class="mb-4 flex items-center gap-2 rounded-lg border border-warning/20 bg-warning/10 p-3 text-sm text-charcoal"
                    role="status"
                >
                    <Icon :name="['fas', 'lock']" />
                    {{ $t('team.advancement.locked') }}
                </p>

                <div class="mb-3 flex flex-wrap items-center gap-2">
                    <form
                        class="relative w-full sm:w-80"
                        role="search"
                        @submit.prevent="applyFilters"
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
                            :aria-label="$t('team.advancement.search')"
                            :placeholder="$t('team.advancement.search')"
                            maxlength="255"
                        />
                    </form>
                    <Button
                        v-for="type in employmentTypes"
                        :key="type"
                        type="button"
                        :variant="
                            selectedEmploymentTypes.includes(type)
                                ? 'primary'
                                : 'outline'
                        "
                        class="min-h-11"
                        :aria-pressed="selectedEmploymentTypes.includes(type)"
                        @click="toggleEmploymentType(type)"
                    >
                        {{ $t(`team.advancement.employment_type.${type}`) }}
                    </Button>
                    <Button
                        v-if="search || selectedEmploymentTypes.length"
                        type="button"
                        variant="ghost"
                        @click="clearFilters"
                    >
                        {{ $t('team.advancement.clear_filters') }}
                    </Button>
                    <div class="ml-auto">
                        <AdvancementViewToggle
                            :model-value="viewMode"
                            @update:model-value="updateViewMode"
                        />
                    </div>
                </div>
                <p class="mt-0 mb-4 text-xs text-muted">
                    {{ $t('team.advancement.unlock_hint') }}
                </p>

                <div
                    v-if="viewMode === 'columns'"
                    class="overflow-x-auto pb-2"
                    :aria-busy="busy"
                >
                    <Board
                        :columns="boardColumns"
                        :items="engagementItems"
                        :disabled="!canWrite"
                        :disabled-keys="movingIds"
                        class="min-w-[52rem] grid-cols-4"
                        @move="moveEngagement"
                    >
                        <template #header="{ column }">
                            <div
                                class="flex items-center justify-between gap-2 text-sm font-bold"
                            >
                                <span class="flex min-w-0 items-center gap-2">
                                    <Icon
                                        :name="column.icon"
                                        size="sm"
                                    />
                                    <span class="truncate">{{
                                        column.label
                                    }}</span>
                                </span>
                                <span
                                    :class="[
                                        'min-w-6 rounded-full px-1.5 py-0.5 text-center text-xs',
                                        column.countClass,
                                    ]"
                                >
                                    {{ localStatusCounts[column.value] }}
                                </span>
                            </div>
                        </template>
                        <template #item="{ item }">
                            <AdvancementBoardCard
                                :name="item.name"
                                :subtitle="item.group?.name ?? ''"
                                :href="`/team/members/${item.id}`"
                            >
                                <Badge
                                    :class="[
                                        'mt-2',
                                        employmentTypePresentation[
                                            item.employment_type
                                        ],
                                    ]"
                                    pill
                                >
                                    {{
                                        $t(
                                            `team.advancement.employment_type.${item.employment_type}`,
                                        )
                                    }}
                                </Badge>
                            </AdvancementBoardCard>
                        </template>
                        <template #empty>
                            <div
                                class="rounded-lg border-2 border-dashed border-line bg-ground/50 px-3 py-5 text-center text-xs text-muted"
                            >
                                {{
                                    $t(
                                        !canWrite
                                            ? 'team.advancement.board.empty'
                                            : 'team.advancement.board.drop_here',
                                    )
                                }}
                            </div>
                        </template>
                    </Board>
                </div>

                <div
                    v-else
                    :aria-busy="busy"
                >
                    <div class="flex flex-col gap-3 md:hidden">
                        <article
                            v-for="engagement in engagements.data"
                            :key="engagement.id"
                            class="rounded-xl border border-line bg-ground p-4"
                        >
                            <div class="flex items-start justify-between gap-3">
                                <div class="flex min-w-0 items-center gap-3">
                                    <Avatar
                                        :name="engagement.name"
                                        size="sm"
                                    />
                                    <div class="min-w-0">
                                        <Link
                                            :href="`/team/members/${engagement.id}`"
                                            class="font-semibold break-words text-charcoal no-underline hover:text-primary hover:underline"
                                        >
                                            {{ engagement.name }}
                                        </Link>
                                        <p
                                            class="mt-0.5 mb-0 text-xs text-muted"
                                        >
                                            {{
                                                engagement.group?.name ||
                                                $t('team.advancement.not_set')
                                            }}
                                        </p>
                                    </div>
                                </div>
                                <Badge
                                    :class="
                                        engagementStatusPresentation[
                                            engagement.status
                                        ].headerClass
                                    "
                                    pill
                                >
                                    {{
                                        $t(
                                            `team.advancement.status.${engagement.status}`,
                                        )
                                    }}
                                </Badge>
                            </div>
                        </article>
                        <p
                            v-if="!engagements.data.length"
                            class="rounded-xl border border-line bg-ground px-4 py-16 text-center text-muted"
                        >
                            {{
                                $t(
                                    search || selectedEmploymentTypes.length
                                        ? 'team.advancement.no_matches'
                                        : 'team.advancement.empty',
                                )
                            }}
                        </p>
                    </div>

                    <div
                        class="hidden overflow-hidden rounded-xl border border-line bg-ground md:block"
                    >
                        <div class="overflow-x-auto">
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>{{
                                            $t(
                                                'team.advancement.columns.member',
                                            )
                                        }}</TableHead>
                                        <TableHead>{{
                                            $t('team.advancement.columns.type')
                                        }}</TableHead>
                                        <TableHead>{{
                                            $t(
                                                'team.advancement.columns.status',
                                            )
                                        }}</TableHead>
                                        <TableHead>{{
                                            $t('team.advancement.columns.group')
                                        }}</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    <TableRow
                                        v-for="engagement in engagements.data"
                                        :key="engagement.id"
                                    >
                                        <TableCell class="min-w-56">
                                            <div
                                                class="flex items-center gap-3"
                                            >
                                                <Avatar
                                                    :name="engagement.name"
                                                    size="sm"
                                                />
                                                <Link
                                                    :href="`/team/members/${engagement.id}`"
                                                    class="font-semibold text-charcoal no-underline hover:text-primary hover:underline"
                                                >
                                                    {{ engagement.name }}
                                                </Link>
                                            </div>
                                        </TableCell>
                                        <TableCell class="text-muted">
                                            {{
                                                $t(
                                                    `team.advancement.employment_type.${engagement.employment_type}`,
                                                )
                                            }}
                                        </TableCell>
                                        <TableCell>
                                            <Badge
                                                :class="
                                                    engagementStatusPresentation[
                                                        engagement.status
                                                    ].headerClass
                                                "
                                                pill
                                            >
                                                {{
                                                    $t(
                                                        `team.advancement.status.${engagement.status}`,
                                                    )
                                                }}
                                            </Badge>
                                        </TableCell>
                                        <TableCell class="text-muted">
                                            {{
                                                engagement.group?.name ||
                                                $t('team.advancement.not_set')
                                            }}
                                        </TableCell>
                                    </TableRow>
                                    <TableRow v-if="!engagements.data.length">
                                        <TableCell
                                            colspan="4"
                                            class="py-16 text-center text-muted"
                                        >
                                            {{
                                                $t(
                                                    search ||
                                                        selectedEmploymentTypes.length
                                                        ? 'team.advancement.no_matches'
                                                        : 'team.advancement.empty',
                                                )
                                            }}
                                        </TableCell>
                                    </TableRow>
                                </TableBody>
                            </Table>
                        </div>
                    </div>
                </div>

                <div
                    v-if="
                        viewMode === 'list' && engagements.meta?.last_page > 1
                    "
                    class="mt-4 flex flex-wrap items-center justify-between gap-3"
                >
                    <p class="m-0 text-sm text-muted">
                        {{
                            $t('team.advancement.pagination', {
                                from: engagements.meta.from,
                                to: engagements.meta.to,
                                total: engagements.meta.total,
                            })
                        }}
                    </p>
                    <nav
                        class="flex gap-2"
                        :aria-label="$t('team.advancement.pagination_label')"
                    >
                        <Button
                            :href="engagements.links.prev || ''"
                            :disabled="!engagements.links.prev"
                            variant="outline"
                        >
                            {{ $t('team.advancement.previous') }}
                        </Button>
                        <Button
                            :href="engagements.links.next || ''"
                            :disabled="!engagements.links.next"
                            variant="outline"
                        >
                            {{ $t('team.advancement.next') }}
                        </Button>
                    </nav>
                </div>
            </template>
        </div>
    </AppLayout>
</template>
