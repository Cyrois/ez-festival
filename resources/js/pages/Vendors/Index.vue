<script setup>
import AppLayout from '../../layouts/AppLayout.vue';
import { Avatar } from '../../components/ui/avatar';
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
import { computed, ref } from 'vue';
import { trans } from 'laravel-vue-i18n';

const props = defineProps({
    event: { type: Object, default: null },
});

const search = ref('');
const viewMode = ref('list');
const vendors = ref([]);
const viewOptions = computed(() => [
    { value: 'columns', label: trans('vendors.views.columns') },
    { value: 'list', label: trans('vendors.views.list') },
]);
const breadcrumbs = computed(() => [
    { label: trans('app.name'), href: '/dashboard' },
    { label: trans('nav.vendors'), href: '/vendors' },
    { label: trans('vendors.title') },
]);

const clearSearch = () => {
    search.value = '';
};
</script>

<template>
    <AppLayout
        :title="$t('vendors.title')"
        :breadcrumbs="breadcrumbs"
    >
        <div class="mb-5 flex flex-wrap items-start justify-between gap-4">
            <div>
                <h1 class="m-0 text-2xl font-bold tracking-tight">
                    {{ $t('vendors.title') }}
                </h1>
                <p class="mt-1 mb-0 text-sm text-muted">
                    {{ $t('vendors.lead') }}
                </p>
            </div>
            <Button
                v-if="event && !event.locked"
                href="/vendors/create"
                class="min-h-11 w-full sm:w-auto"
                disabled
            >
                {{ $t('vendors.add') }}
            </Button>
        </div>

        <EmptyState
            v-if="!event"
            :title="$t('vendors.no_event.title')"
            :description="$t('vendors.no_event.body')"
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
                {{ $t('vendors.locked') }}
            </p>
            <div class="mb-4 flex flex-wrap items-center gap-2">
                <form
                    class="relative w-full sm:w-64"
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
                        :aria-label="$t('vendors.search')"
                        :placeholder="$t('vendors.search')"
                        maxlength="255"
                    />
                </form>
                <Button
                    variant="outline"
                    class="min-h-11"
                    disabled
                >
                    {{ $t('vendors.columns.labels') }}
                    <Icon
                        :name="['fas', 'chevron-down']"
                        size="sm"
                        class="ml-2"
                    />
                </Button>
                <Button
                    v-if="search"
                    variant="ghost"
                    @click="clearSearch"
                >
                    {{ $t('vendors.clear_filters') }}
                </Button>
                <div
                    class="ml-auto"
                    :title="$t('vendors.columns_deferred')"
                >
                    <SegmentedControl
                        v-model="viewMode"
                        :options="viewOptions"
                        :aria-label="$t('vendors.views.mode')"
                    />
                </div>
            </div>

            <div class="flex flex-col gap-3 md:hidden">
                <div
                    v-for="vendor in vendors"
                    :key="vendor.id"
                    class="rounded-xl border border-line bg-ground p-4"
                >
                    <div class="flex items-center gap-3">
                        <Avatar
                            :name="vendor.name"
                            size="sm"
                        />
                        <span class="font-semibold">{{ vendor.name }}</span>
                        <Badge
                            variant="neutral"
                            pill
                            >{{ vendor.status }}</Badge
                        >
                    </div>
                </div>
                <p
                    class="rounded-xl border border-line bg-ground px-4 py-16 text-center text-muted"
                >
                    {{ $t(search ? 'vendors.no_matches' : 'vendors.empty') }}
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
                                    $t('vendors.columns.vendor')
                                }}</TableHead>
                                <TableHead>{{
                                    $t('vendors.columns.type')
                                }}</TableHead>
                                <TableHead>{{
                                    $t('vendors.columns.status')
                                }}</TableHead>
                                <TableHead>{{
                                    $t('vendors.columns.labels')
                                }}</TableHead>
                                <TableHead>{{
                                    $t('vendors.columns.custom')
                                }}</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableRow v-if="!vendors.length">
                                <TableCell
                                    :colspan="5"
                                    class="py-16 text-center text-muted"
                                >
                                    {{
                                        $t(
                                            search
                                                ? 'vendors.no_matches'
                                                : 'vendors.empty',
                                        )
                                    }}
                                </TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>
                </div>
            </div>
        </template>
    </AppLayout>
</template>
