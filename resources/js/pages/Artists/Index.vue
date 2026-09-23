<script setup>
import AppLayout from '../../layouts/AppLayout.vue';
import { Avatar } from '../../components/ui/avatar';
import { Badge } from '../../components/ui/badge';
import { Button } from '../../components/ui/button';
import { EmptyState } from '../../components/ui/empty-state';
import { Icon } from '../../components/ui/icon';
import { Input } from '../../components/ui/input';
import { LabelCombobox } from '../../components/ui/label-combobox';
import { SegmentedControl } from '../../components/ui/segmented-control';
import { Tag } from '../../components/ui/tag';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '../../components/ui/table';
import { Link, router } from '@inertiajs/vue3';
import { computed, onUnmounted, ref, watch } from 'vue';
import { trans } from 'laravel-vue-i18n';

const props = defineProps({
    engagements: { type: Object, required: true },
    labels: { type: Array, required: true },
    filters: { type: Object, required: true },
    event: { type: Object, default: null },
});
const search = ref(props.filters.search);
const selectedLabels = ref([...props.filters.labels]);
const busy = ref(false);
const viewMode = ref('list');
const viewOptions = computed(() => [
    {
        value: 'columns',
        label: trans('artists.views.columns'),
    },
    {
        value: 'list',
        label: trans('artists.views.list'),
    },
]);
const breadcrumbs = computed(() => [
    { label: trans('app.name'), href: '/dashboard' },
    { label: trans('nav.artists'), href: '/artists/advancing' },
    { label: trans('artists.advancing') },
]);
const statusVariant = {
    idea: 'neutral',
    outreach: 'primary',
    negotiating: 'warning',
    contract_sent: 'primary',
    confirmed: 'success',
    declined: 'danger',
};
let searchTimer;
const applyFilters = () => {
    clearTimeout(searchTimer);
    router.get(
        '/artists/advancing',
        { search: search.value, labels: selectedLabels.value },
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,
            onStart: () => {
                busy.value = true;
            },
            onFinish: () => {
                busy.value = false;
            },
        },
    );
};
watch(search, () => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(applyFilters, 300);
});
watch(
    () => props.filters,
    (filters) => {
        search.value = filters.search;
        selectedLabels.value = [...filters.labels];
    },
);
watch(viewMode, (value) => {
    // Columns board deferred — keep List selected.
    if (value !== 'list') {
        viewMode.value = 'list';
    }
});
onUnmounted(() => {
    clearTimeout(searchTimer);
});
const updateLabelFilters = (labels) => {
    selectedLabels.value = labels;
    applyFilters();
};
const clearFilters = () => {
    search.value = '';
    selectedLabels.value = [];
    applyFilters();
};
</script>

<template>
    <AppLayout
        :title="$t('artists.title')"
        :breadcrumbs="breadcrumbs"
    >
        <div class="mb-5 flex flex-wrap items-start justify-between gap-4">
            <div>
                <h1 class="m-0 text-2xl font-bold tracking-tight">
                    {{ $t('artists.title') }}
                </h1>
                <p class="mt-1 mb-0 text-sm text-muted">
                    {{ $t('artists.lead') }}
                </p>
            </div>
            <Button
                v-if="event && !event.locked"
                href="/artists/create"
                class="min-h-11 w-full sm:w-auto"
            >
                {{ $t('artists.add') }}
            </Button>
        </div>

        <EmptyState
            v-if="!event"
            :title="$t('artists.no_event.title')"
            :description="$t('artists.no_event.body')"
        >
            <Button href="/settings/events">{{
                $t('settings.events.title')
            }}</Button>
        </EmptyState>

        <template v-else>
            <p
                v-if="event.locked"
                class="mb-4 flex items-center gap-2 rounded-lg border border-warning/20 bg-warning/10 p-3 text-sm text-charcoal"
                role="status"
            >
                <Icon :name="['fas', 'lock']" />
                {{ $t('artists.locked') }}
            </p>
            <div class="mb-4 flex flex-wrap items-center gap-2">
                <form
                    class="relative w-full sm:w-64"
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
                        :aria-label="$t('artists.search')"
                        :placeholder="$t('artists.search')"
                        maxlength="255"
                    />
                </form>
                <div class="w-full sm:w-72">
                    <LabelCombobox
                        :model-value="selectedLabels"
                        :labels="labels"
                        :placeholder="$t('artists.filter_labels')"
                        class="min-h-11"
                        :aria-label="$t('artists.filter_labels')"
                        @update:model-value="updateLabelFilters"
                    />
                    <p class="mt-1 mb-0 text-xs text-muted">
                        {{ $t('artists.filter_hint') }}
                    </p>
                </div>
                <Button
                    v-if="search || selectedLabels.length"
                    variant="ghost"
                    @click="clearFilters"
                    >{{ $t('artists.clear_filters') }}</Button
                >
                <!-- TODO: Implement the Columns board in a follow-up. -->
                <div
                    class="ml-auto"
                    :title="$t('artists.columns_deferred')"
                >
                    <SegmentedControl
                        v-model="viewMode"
                        :options="viewOptions"
                        :aria-label="$t('artists.views.mode')"
                    />
                </div>
            </div>
            <div :aria-busy="busy">
                <!-- Phone: card stack -->
                <div class="flex flex-col gap-3 md:hidden">
                    <div
                        v-for="engagement in engagements.data"
                        :key="`card-${engagement.id}`"
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
                                        :href="`/artists/engagements/${engagement.id}`"
                                        class="font-semibold break-words text-charcoal no-underline hover:text-primary hover:underline"
                                    >
                                        {{ engagement.name }}
                                    </Link>
                                    <div class="mt-0.5 text-xs text-muted">
                                        {{
                                            engagement.type ||
                                            $t('artists.not_set')
                                        }}
                                    </div>
                                </div>
                            </div>
                            <Badge
                                :variant="statusVariant[engagement.status]"
                                pill
                            >
                                {{ $t(`artists.status.${engagement.status}`) }}
                            </Badge>
                        </div>
                        <div class="mt-3 flex flex-wrap gap-1.5">
                            <Tag
                                v-for="label in engagement.labels"
                                :key="label.id"
                                :name="label.name"
                                :color="label.color"
                            />
                            <span
                                v-if="!engagement.labels.length"
                                class="text-sm text-muted"
                                >{{ $t('artists.not_set') }}</span
                            >
                        </div>
                        <p class="mt-2 mb-0 text-sm text-muted">
                            <span class="font-semibold text-charcoal/70">{{
                                $t('artists.columns.custom')
                            }}</span>
                            ·
                            {{
                                engagement.custom.length
                                    ? ''
                                    : $t('artists.custom_empty')
                            }}
                        </p>
                    </div>
                    <p
                        v-if="!engagements.data.length"
                        class="rounded-xl border border-line bg-ground px-4 py-16 text-center text-muted"
                    >
                        {{
                            $t(
                                search || selectedLabels.length
                                    ? 'artists.no_matches'
                                    : 'artists.empty',
                            )
                        }}
                    </p>
                </div>

                <!-- md+: table -->
                <div
                    class="hidden overflow-hidden rounded-xl border border-line bg-ground md:block"
                >
                    <div class="overflow-x-auto">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead
                                        v-for="column in [
                                            'artist',
                                            'type',
                                            'status',
                                            'labels',
                                            'custom',
                                        ]"
                                        :key="column"
                                        >{{
                                            $t(`artists.columns.${column}`)
                                        }}</TableHead
                                    >
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                <TableRow
                                    v-for="engagement in engagements.data"
                                    :key="engagement.id"
                                >
                                    <TableCell class="min-w-56">
                                        <div class="flex items-center gap-3">
                                            <Avatar
                                                :name="engagement.name"
                                                size="sm"
                                            />
                                            <Link
                                                :href="`/artists/engagements/${engagement.id}`"
                                                class="max-w-72 font-semibold break-words text-charcoal no-underline hover:text-primary hover:underline"
                                            >
                                                {{ engagement.name }}
                                            </Link>
                                        </div>
                                    </TableCell>
                                    <TableCell class="min-w-32 text-muted">{{
                                        engagement.type || $t('artists.not_set')
                                    }}</TableCell>
                                    <TableCell>
                                        <Badge
                                            :variant="
                                                statusVariant[engagement.status]
                                            "
                                            pill
                                            >{{
                                                $t(
                                                    `artists.status.${engagement.status}`,
                                                )
                                            }}</Badge
                                        >
                                    </TableCell>
                                    <TableCell class="min-w-44">
                                        <div class="flex flex-wrap gap-1.5">
                                            <Tag
                                                v-for="label in engagement.labels"
                                                :key="label.id"
                                                :name="label.name"
                                                :color="label.color"
                                            />
                                            <span
                                                v-if="!engagement.labels.length"
                                                class="text-muted"
                                                >{{
                                                    $t('artists.not_set')
                                                }}</span
                                            >
                                        </div>
                                    </TableCell>
                                    <TableCell class="min-w-36 text-muted">
                                        <span
                                            v-if="!engagement.custom.length"
                                            >{{
                                                $t('artists.custom_empty')
                                            }}</span
                                        >
                                    </TableCell>
                                </TableRow>
                                <TableRow v-if="!engagements.data.length">
                                    <TableCell
                                        :colspan="5"
                                        class="py-16 text-center text-muted"
                                    >
                                        {{
                                            $t(
                                                search || selectedLabels.length
                                                    ? 'artists.no_matches'
                                                    : 'artists.empty',
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
                v-if="engagements.meta.last_page > 1"
                class="mt-4 flex flex-wrap items-center justify-between gap-3"
            >
                <p class="m-0 text-sm text-muted">
                    {{
                        $t('artists.pagination', {
                            from: engagements.meta.from,
                            to: engagements.meta.to,
                            total: engagements.meta.total,
                        })
                    }}
                </p>
                <nav
                    class="flex gap-2"
                    :aria-label="$t('artists.pagination_label')"
                >
                    <Button
                        :href="engagements.links.prev || ''"
                        :disabled="!engagements.links.prev"
                        variant="outline"
                        >{{ $t('artists.previous') }}</Button
                    >
                    <Button
                        :href="engagements.links.next || ''"
                        :disabled="!engagements.links.next"
                        variant="outline"
                        >{{ $t('artists.next') }}</Button
                    >
                </nav>
            </div>
        </template>
    </AppLayout>
</template>
