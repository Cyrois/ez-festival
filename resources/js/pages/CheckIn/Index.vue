<script setup>
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import { trans } from 'laravel-vue-i18n';
import AppLayout from '../../layouts/AppLayout.vue';
import { Avatar } from '../../components/ui/avatar';
import { Badge } from '../../components/ui/badge';
import { Button } from '../../components/ui/button';
import { Icon } from '../../components/ui/icon';
import { Input } from '../../components/ui/input';
import { Select } from '../../components/ui/select';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '../../components/ui/table';
import { checkInQuery } from './filters';

const props = defineProps({
    people: { type: Object, required: true },
    passes: { type: Array, required: true },
    filters: { type: Object, required: true },
    event: { type: Object, required: true },
});

const type = ref(props.filters.type ?? 'all');
const pass = ref(props.filters.pass ? String(props.filters.pass) : '');
const status = ref(props.filters.status ?? 'all');
const search = ref(props.filters.search ?? '');
const types = ['all', 'artist'];
const statuses = ['all', 'not_started', 'partial', 'complete'];
const statusVariants = {
    not_started: 'neutral',
    partial: 'warning',
    complete: 'success',
};
const breadcrumbs = computed(() => [
    { label: trans('app.name'), href: '/dashboard' },
    { label: trans('check_in.title') },
]);
let searchTimer;

const applyFilters = () => {
    router.get(
        '/check-in',
        checkInQuery({
            type: type.value,
            pass: pass.value,
            status: status.value,
            search: search.value,
        }),
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        },
    );
};

const scanQr = () => {
    const code = window.prompt(trans('check_in.scan_prompt'));

    if (code === null) return;
    search.value = code;
};

watch([type, pass, status], applyFilters);
watch(search, () => {
    window.clearTimeout(searchTimer);
    searchTimer = window.setTimeout(applyFilters, 300);
});
onBeforeUnmount(() => window.clearTimeout(searchTimer));
</script>

<template>
    <AppLayout
        :title="$t('check_in.title')"
        :breadcrumbs="breadcrumbs"
    >
        <div class="container mx-auto">
            <h1 class="m-0 text-2xl font-bold tracking-tight">
                {{ $t('check_in.title') }}
            </h1>
            <p class="mt-1 mb-0 text-sm text-muted">
                {{ $t('check_in.lead') }}
            </p>
            <p class="mt-1 mb-5 text-xs text-muted/70">
                {{ $t('check_in.shift_note') }}
            </p>

            <div class="mb-3 flex flex-wrap items-center gap-2">
                <span class="mr-1 text-xs font-bold text-muted">
                    {{ $t('check_in.filters.type') }}
                </span>
                <Button
                    v-for="value in types"
                    :key="value"
                    size="sm"
                    :variant="type === value ? 'outline-secondary' : 'outline'"
                    class="rounded-full font-normal"
                    @click="type = value"
                >
                    {{ $t(`check_in.types.${value}`) }}
                </Button>
                <label class="ml-2 text-xs font-bold text-muted">
                    {{ $t('check_in.filters.pass') }}
                </label>
                <div class="w-44">
                    <Select
                        v-model="pass"
                        :aria-label="$t('check_in.filters.pass')"
                    >
                        <option value="">
                            {{ $t('check_in.filters.all_passes') }}
                        </option>
                        <option
                            v-for="passType in passes"
                            :key="passType.id"
                            :value="passType.id"
                        >
                            {{ passType.name }}
                        </option>
                    </Select>
                </div>
            </div>

            <div class="mb-3 flex flex-wrap items-center gap-2">
                <form
                    class="relative min-w-64 flex-1"
                    @submit.prevent="applyFilters"
                >
                    <Icon
                        :name="['fas', 'magnifying-glass']"
                        class="pointer-events-none absolute top-3 left-3 z-10 text-muted"
                        size="sm"
                    />
                    <Input
                        v-model="search"
                        type="search"
                        class="pl-9"
                        :placeholder="$t('check_in.filters.search_placeholder')"
                        :aria-label="$t('check_in.filters.search_placeholder')"
                    />
                </form>
                <Button
                    variant="outline"
                    @click="scanQr"
                >
                    <Icon :name="['fas', 'qrcode']" />
                    {{ $t('check_in.filters.scan_qr') }}
                </Button>
                <Button
                    v-for="value in statuses"
                    :key="value"
                    size="sm"
                    :variant="
                        status === value ? 'outline-secondary' : 'outline'
                    "
                    class="rounded-full font-normal"
                    @click="status = value"
                >
                    {{ $t(`check_in.status.${value}`) }}
                </Button>
            </div>

            <p class="mt-0 mb-4 text-xs text-muted">
                {{ $t('check_in.results_note') }}
            </p>

            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead>{{
                            $t('check_in.columns.person')
                        }}</TableHead>
                        <TableHead>{{ $t('check_in.columns.type') }}</TableHead>
                        <TableHead>{{
                            $t('check_in.columns.context')
                        }}</TableHead>
                        <TableHead>{{ $t('check_in.columns.pass') }}</TableHead>
                        <TableHead>{{
                            $t('check_in.columns.entitlements')
                        }}</TableHead>
                        <TableHead>{{
                            $t('check_in.columns.status')
                        }}</TableHead>
                        <TableHead class="text-right">
                            {{ $t('check_in.columns.action') }}
                        </TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableRow
                        v-for="person in people.data"
                        :key="`${person.type}-${person.engagement_id}-${person.person_id}`"
                    >
                        <TableCell>
                            <div class="flex min-w-44 items-center gap-2.5">
                                <Avatar
                                    :name="person.name"
                                    size="sm"
                                />
                                <div>
                                    <span class="block font-bold">
                                        {{ person.name }}
                                    </span>
                                    <span class="block text-xs text-muted">
                                        {{ person.subtitle }}
                                    </span>
                                </div>
                            </div>
                        </TableCell>
                        <TableCell>
                            <Badge
                                variant="outline"
                                pill
                                class="font-normal text-muted"
                            >
                                {{ $t(`check_in.types.${person.type}`) }}
                            </Badge>
                        </TableCell>
                        <TableCell class="text-muted">
                            {{ person.context }}
                        </TableCell>
                        <TableCell class="text-muted">
                            {{ person.pass_name }}
                        </TableCell>
                        <TableCell class="font-bold">
                            {{ person.issued }}/{{ person.expected }}
                        </TableCell>
                        <TableCell>
                            <Badge
                                :variant="
                                    statusVariants[person.check_in_status]
                                "
                                pill
                                class="uppercase"
                            >
                                {{
                                    $t(
                                        `check_in.status.${person.check_in_status}`,
                                    )
                                }}
                            </Badge>
                        </TableCell>
                        <TableCell class="text-right">
                            <Button
                                :variant="
                                    person.check_in_status === 'complete'
                                        ? 'outline-secondary'
                                        : 'primary'
                                "
                                size="sm"
                                :href="`/check-in/artists/${person.engagement_id}?person=${person.person_id}`"
                            >
                                {{
                                    person.check_in_status === 'complete'
                                        ? $t('check_in.actions.view')
                                        : $t('check_in.actions.check_in')
                                }}
                            </Button>
                            <Link
                                v-if="person.can_edit"
                                :href="`/artists/engagements/${person.engagement_id}#passes`"
                                class="mt-1 block text-xs font-semibold text-secondary no-underline hover:underline"
                            >
                                {{ $t('check_in.actions.edit_passes') }}
                            </Link>
                            <template v-else>
                                <span
                                    class="mt-1 block text-xs font-semibold text-muted"
                                >
                                    {{ $t('check_in.actions.edit_passes') }}
                                </span>
                                <span class="block text-[11px] text-muted/70">
                                    {{
                                        $t(
                                            'check_in.actions.needs_edit_permission',
                                        )
                                    }}
                                </span>
                            </template>
                        </TableCell>
                    </TableRow>
                    <TableRow v-if="people.data.length === 0">
                        <TableCell
                            colspan="7"
                            class="py-16 text-center text-muted"
                        >
                            {{ $t('check_in.empty') }}
                        </TableCell>
                    </TableRow>
                </TableBody>
            </Table>

            <div
                v-if="people.meta.last_page > 1"
                class="mt-4 flex flex-wrap items-center justify-between gap-3"
            >
                <p class="m-0 text-sm text-muted">
                    {{
                        $t('check_in.pagination', {
                            from: people.meta.from,
                            to: people.meta.to,
                            total: people.meta.total,
                        })
                    }}
                </p>
                <nav
                    class="flex gap-2"
                    :aria-label="$t('check_in.pagination_label')"
                >
                    <Button
                        :href="people.links.prev || ''"
                        :disabled="!people.links.prev"
                        variant="outline"
                    >
                        {{ $t('check_in.previous') }}
                    </Button>
                    <Button
                        :href="people.links.next || ''"
                        :disabled="!people.links.next"
                        variant="outline"
                    >
                        {{ $t('check_in.next') }}
                    </Button>
                </nav>
            </div>
        </div>
    </AppLayout>
</template>
