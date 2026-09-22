<script setup>
import AppLayout from '../../layouts/AppLayout.vue';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '../../components/ui/table';
import { computed } from 'vue';
import { trans } from 'laravel-vue-i18n';

const props = defineProps({
    item: { type: Object, required: true },
    issued: { type: Array, default: () => [] },
});

const breadcrumbs = computed(() => [
    { label: trans('app.name'), href: '/dashboard' },
    { label: trans('nav.credentials'), href: '/credentials/passes' },
    {
        label: trans('nav.credentials.entitlements'),
        href: '/credentials/entitlements',
    },
    { label: props.item.name },
]);

const formatWhen = (value) =>
    new Intl.DateTimeFormat(undefined, {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(value));
</script>

<template>
    <AppLayout
        :title="item.name"
        :breadcrumbs="breadcrumbs"
        back-href="/credentials/entitlements"
        :back-label="$t('credentials.entitlements.view.back')"
    >
        <div class="container mx-auto">
            <div class="mb-7">
                <h1 class="m-0 text-2xl font-bold tracking-tight">
                    {{ item.name }}
                </h1>
                <p class="mt-1 mb-0 text-sm text-muted">
                    {{ $t('credentials.entitlements.view.title') }} ·
                    {{ item.balance }}
                </p>
            </div>

            <section aria-labelledby="issued-log-heading">
                <h2
                    id="issued-log-heading"
                    class="m-0 text-lg font-bold"
                >
                    {{ $t('credentials.entitlements.view.issued') }}
                </h2>
                <p class="mt-1 mb-3 text-sm text-muted">
                    {{ $t('credentials.entitlements.view.issued_lead') }}
                </p>
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>{{
                                $t('credentials.entitlements.view.table.when')
                            }}</TableHead>
                            <TableHead>{{
                                $t('credentials.entitlements.view.table.pass')
                            }}</TableHead>
                            <TableHead>{{
                                $t('credentials.entitlements.view.table.code')
                            }}</TableHead>
                            <TableHead>{{
                                $t(
                                    'credentials.entitlements.view.table.issued_by',
                                )
                            }}</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow
                            v-for="issue in issued"
                            :key="issue.id"
                        >
                            <TableCell>{{
                                formatWhen(issue.issued_at)
                            }}</TableCell>
                            <TableCell>{{
                                issue.pass_name ??
                                $t('credentials.entitlements.view.not_recorded')
                            }}</TableCell>
                            <TableCell>{{
                                issue.code ??
                                $t('credentials.entitlements.view.not_recorded')
                            }}</TableCell>
                            <TableCell>{{
                                issue.issued_by?.name ??
                                $t('credentials.entitlements.view.not_recorded')
                            }}</TableCell>
                        </TableRow>
                        <TableRow v-if="issued.length === 0">
                            <TableCell
                                colspan="4"
                                class="py-8 text-center text-sm text-muted"
                            >
                                {{
                                    $t(
                                        'credentials.entitlements.view.issued_empty',
                                    )
                                }}
                            </TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
            </section>
        </div>
    </AppLayout>
</template>
