<script setup>
import { ref } from 'vue';
import { trans } from 'laravel-vue-i18n';
import { Button } from '../../components/ui/button';
import { Badge } from '../../components/ui/badge';
import { Avatar } from '../../components/ui/avatar';
import { Tabs, TabList, Tab, TabPanel } from '../../components/ui/tabs';
import { SegmentedControl } from '../../components/ui/segmented-control';
import { Card } from '../../components/ui/card';
import { EmptyState } from '../../components/ui/empty-state';
import {
    Table,
    TableHeader,
    TableBody,
    TableRow,
    TableHead,
    TableCell,
} from '../../components/ui/table';

const activeTab = ref('artists');
const viewMode = ref('list');

const viewOptions = [
    { value: 'list', label: trans('ui.demo.segmented.list') },
    { value: 'board', label: trans('ui.demo.segmented.board') },
    { value: 'calendar', label: trans('ui.demo.segmented.calendar') },
];
</script>

<template>
    <section class="mb-4 rounded-xl border border-line bg-ground px-5 py-5">
        <h2 class="m-0 mb-1 text-base font-bold">
            {{ $t('ui.demo.avatars') }}
        </h2>
        <p class="m-0 mb-4 text-xs text-muted">
            {{ $t('ui.demo.avatars_lead') }}
        </p>
        <div class="flex flex-wrap items-center gap-3">
            <Avatar
                :name="$t('ui.demo.avatar.person_a')"
                tone="teal"
                size="sm"
            />
            <Avatar
                :name="$t('ui.demo.avatar.person_b')"
                tone="teal"
                size="md"
            />
            <Avatar
                :name="$t('ui.demo.avatar.person_c')"
                tone="charcoal"
                size="lg"
            />
            <Avatar
                :name="$t('ui.demo.avatar.person_a')"
                tone="charcoal"
                size="md"
            />
        </div>
    </section>

    <section class="mb-4 rounded-xl border border-line bg-ground px-5 py-5">
        <h2 class="m-0 mb-1 text-base font-bold">
            {{ $t('ui.demo.tabs') }}
        </h2>
        <p class="m-0 mb-4 text-xs text-muted">
            {{ $t('ui.demo.tabs_lead') }}
        </p>
        <Tabs v-model="activeTab">
            <TabList>
                <Tab value="artists">
                    {{ $t('ui.demo.tab.artists') }}
                </Tab>
                <Tab value="vendors">
                    {{ $t('ui.demo.tab.vendors') }}
                </Tab>
                <Tab value="crew">
                    {{ $t('ui.demo.tab.crew') }}
                </Tab>
            </TabList>
            <TabPanel value="artists">
                {{ $t('ui.demo.tab.artists_body') }}
            </TabPanel>
            <TabPanel value="vendors">
                {{ $t('ui.demo.tab.vendors_body') }}
            </TabPanel>
            <TabPanel value="crew">
                {{ $t('ui.demo.tab.crew_body') }}
            </TabPanel>
        </Tabs>
    </section>

    <section class="mb-4 rounded-xl border border-line bg-ground px-5 py-5">
        <h2 class="m-0 mb-1 text-base font-bold">
            {{ $t('ui.demo.segmented') }}
        </h2>
        <p class="m-0 mb-4 text-xs text-muted">
            {{ $t('ui.demo.segmented_lead') }}
        </p>
        <SegmentedControl
            v-model="viewMode"
            :options="viewOptions"
        />
    </section>

    <section class="mb-4 rounded-xl border border-line bg-ground px-5 py-5">
        <h2 class="m-0 mb-1 text-base font-bold">
            {{ $t('ui.demo.cards') }}
        </h2>
        <p class="m-0 mb-4 text-xs text-muted">
            {{ $t('ui.demo.cards_lead') }}
        </p>
        <Card
            :title="$t('ui.demo.card.title')"
            class="max-w-md"
        >
            <p class="m-0 text-sm text-muted">
                {{ $t('ui.demo.card.body') }}
            </p>
            <template #footer>
                <p class="m-0 text-xs text-muted">
                    {{ $t('ui.demo.card.footer') }}
                </p>
            </template>
        </Card>
    </section>

    <section class="mb-4 rounded-xl border border-line bg-ground px-5 py-5">
        <h2 class="m-0 mb-1 text-base font-bold">
            {{ $t('ui.demo.empty') }}
        </h2>
        <p class="m-0 mb-4 text-xs text-muted">
            {{ $t('ui.demo.empty_lead') }}
        </p>
        <EmptyState
            :title="$t('ui.demo.empty.title')"
            :description="$t('ui.demo.empty.description')"
        >
            <template #icon>
                <span
                    class="inline-flex h-10 w-10 items-center justify-center rounded-full bg-page text-lg font-bold text-muted"
                    aria-hidden="true"
                >
                    ∅
                </span>
            </template>
            <Button
                variant="primary"
                size="sm"
            >
                {{ $t('ui.demo.empty.action') }}
            </Button>
        </EmptyState>
    </section>

    <section class="mb-4 rounded-xl border border-line bg-ground px-5 py-5">
        <h2 class="m-0 mb-1 text-base font-bold">
            {{ $t('ui.demo.table') }}
        </h2>
        <p class="m-0 mb-4 text-xs text-muted">
            {{ $t('ui.demo.table_lead') }}
        </p>
        <Table>
            <TableHeader>
                <TableRow>
                    <TableHead>
                        {{ $t('ui.demo.table.artist') }}
                    </TableHead>
                    <TableHead>
                        {{ $t('ui.demo.table.stage') }}
                    </TableHead>
                    <TableHead>
                        {{ $t('ui.demo.table.status') }}
                    </TableHead>
                </TableRow>
            </TableHeader>
            <TableBody>
                <TableRow>
                    <TableCell>
                        <div class="flex items-center gap-2">
                            <Avatar
                                :name="$t('ui.demo.table.row1_artist')"
                                size="sm"
                                tone="teal"
                            />
                            <span>
                                {{ $t('ui.demo.table.row1_artist') }}
                            </span>
                        </div>
                    </TableCell>
                    <TableCell>
                        {{ $t('ui.demo.table.row1_stage') }}
                    </TableCell>
                    <TableCell>
                        <Badge
                            variant="success"
                            pill
                        >
                            {{ $t('ui.demo.badge.success') }}
                        </Badge>
                    </TableCell>
                </TableRow>
                <TableRow>
                    <TableCell>
                        <div class="flex items-center gap-2">
                            <Avatar
                                :name="$t('ui.demo.table.row2_artist')"
                                size="sm"
                                tone="charcoal"
                            />
                            <span>
                                {{ $t('ui.demo.table.row2_artist') }}
                            </span>
                        </div>
                    </TableCell>
                    <TableCell>
                        {{ $t('ui.demo.table.row2_stage') }}
                    </TableCell>
                    <TableCell>
                        <Badge
                            variant="warning"
                            pill
                        >
                            {{ $t('ui.demo.badge.warning') }}
                        </Badge>
                    </TableCell>
                </TableRow>
                <TableRow>
                    <TableCell>
                        <div class="flex items-center gap-2">
                            <Avatar
                                :name="$t('ui.demo.table.row3_artist')"
                                size="sm"
                                tone="teal"
                            />
                            <span>
                                {{ $t('ui.demo.table.row3_artist') }}
                            </span>
                        </div>
                    </TableCell>
                    <TableCell>
                        {{ $t('ui.demo.table.row3_stage') }}
                    </TableCell>
                    <TableCell>
                        <Badge
                            variant="primary"
                            pill
                        >
                            {{ $t('ui.demo.badge.primary') }}
                        </Badge>
                    </TableCell>
                </TableRow>
            </TableBody>
        </Table>
    </section>
</template>
