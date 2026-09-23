<script setup>
import AppLayout from '../../layouts/AppLayout.vue';
import { Badge } from '../../components/ui/badge';
import { Button } from '../../components/ui/button';
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
import { computed, ref } from 'vue';
import { trans } from 'laravel-vue-i18n';
import { filterArtistCheckIns } from './checkInFilters';

const props = defineProps({
    engagements: { type: Array, required: true },
    event: { type: Object, required: true },
});
const search = ref('');
const status = ref('');
const statuses = ['', 'not_started', 'partial', 'complete'];
const variants = {
    not_started: 'neutral',
    partial: 'warning',
    complete: 'success',
};
const columns = ['artist', 'contact', 'entitlements', 'status', 'action'];
const filtered = computed(() =>
    filterArtistCheckIns(props.engagements, search.value, status.value),
);
const breadcrumbs = computed(() => [
    { label: trans('app.name'), href: '/dashboard' },
    { label: trans('nav.artists'), href: '/artists/advancing' },
    { label: trans('artists.check_in.title') },
]);
</script>

<template>
    <AppLayout
        :title="$t('artists.check_in.title')"
        :breadcrumbs="breadcrumbs"
    >
        <div class="container mx-auto">
            <h1 class="m-0 text-2xl font-bold tracking-tight">
                {{ $t('artists.check_in.title') }}
            </h1>
            <p class="mt-1 mb-5 text-sm text-muted">
                {{ $t('artists.check_in.lead', { event: event.name }) }}
            </p>
            <div class="mb-4 flex flex-wrap items-center gap-2">
                <div class="relative w-full sm:w-72">
                    <Icon
                        :name="['fas', 'magnifying-glass']"
                        class="pointer-events-none absolute top-3.5 left-3 z-10 text-muted"
                        size="sm"
                    />
                    <Input
                        v-model="search"
                        type="search"
                        class="min-h-11 pl-9"
                        :placeholder="$t('artists.check_in.search')"
                        :aria-label="$t('artists.check_in.search')"
                    />
                </div>
                <Button
                    v-for="value in statuses"
                    :key="value || 'all'"
                    size="sm"
                    :variant="status === value ? 'primary' : 'outline'"
                    @click="status = value"
                >
                    {{
                        $t(
                            value
                                ? `artists.check_in.status.${value}`
                                : 'artists.check_in.status.all',
                        )
                    }}
                </Button>
            </div>
            <div
                class="overflow-hidden rounded-xl border border-line bg-ground"
            >
                <div class="overflow-x-auto">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead
                                    v-for="column in columns"
                                    :key="column"
                                >
                                    {{
                                        $t(`artists.check_in.columns.${column}`)
                                    }}
                                </TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableRow
                                v-for="engagement in filtered"
                                :key="engagement.id"
                            >
                                <TableCell class="font-bold">{{
                                    engagement.name
                                }}</TableCell>
                                <TableCell>
                                    <template v-if="engagement.contact">
                                        {{ engagement.contact.name }}
                                        <span
                                            class="block text-xs text-muted"
                                            >{{
                                                engagement.contact.email
                                            }}</span
                                        >
                                    </template>
                                    <span
                                        v-else
                                        class="text-muted"
                                        >{{
                                            $t('artists.check_in.not_set')
                                        }}</span
                                    >
                                </TableCell>
                                <TableCell class="font-semibold">
                                    {{ engagement.issued }} /
                                    {{ engagement.expected }}
                                </TableCell>
                                <TableCell>
                                    <Badge
                                        :variant="
                                            variants[engagement.check_in_status]
                                        "
                                        pill
                                        >{{
                                            $t(
                                                `artists.check_in.status.${engagement.check_in_status}`,
                                            )
                                        }}</Badge
                                    >
                                </TableCell>
                                <TableCell class="text-right">
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        :href="`/artists/check-in/${engagement.id}`"
                                    >
                                        {{ $t('artists.check_in.open') }}
                                    </Button>
                                </TableCell>
                            </TableRow>
                            <TableRow v-if="filtered.length === 0">
                                <TableCell
                                    colspan="5"
                                    class="py-16 text-center text-muted"
                                    >{{
                                        $t('artists.check_in.empty')
                                    }}</TableCell
                                >
                            </TableRow>
                        </TableBody>
                    </Table>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
