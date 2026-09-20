<script setup>
import AppLayout from '../../layouts/AppLayout.vue';
import { Button } from '../../components/ui/button';
import { Checkbox } from '../../components/ui/checkbox';
import { Icon } from '../../components/ui/icon';
import { IconButton } from '../../components/ui/icon-button';
import { Input } from '../../components/ui/input';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '../../components/ui/table';
import { Tag } from '../../components/ui/tag';
import { usePage } from '@inertiajs/vue3';
import { computed, onMounted, onUnmounted, ref } from 'vue';
import { trans } from 'laravel-vue-i18n';

const props = defineProps({
    passes: { type: Array, default: () => [] },
    labels: { type: Array, default: () => [] },
    canWrite: { type: Boolean, default: false },
});

const page = usePage();
const search = ref('');
const selectedLabelIds = ref([]);
const isLabelFilterOpen = ref(false);
const labelFilter = ref(null);
const eventName = computed(() => page.props.activeEvent?.name ?? '');
const breadcrumbs = computed(() => [
    { label: trans('app.name'), href: '/dashboard' },
    { label: trans('nav.credentials'), href: '/credentials/passes' },
    { label: trans('credentials.passes.title') },
]);
const lead = computed(() =>
    trans('credentials.passes.lead', { event: eventName.value }),
);

const usageFor = (pass) =>
    pass.max_assignments === null
        ? trans('credentials.passes.usage.unlimited', {
              assigned: pass.assigned_count,
          })
        : trans('credentials.passes.usage.limited', {
              assigned: pass.assigned_count,
              capacity: pass.max_assignments,
          });

const filteredPasses = computed(() => {
    const normalizedSearch = search.value.trim().toLocaleLowerCase();

    return props.passes.filter((pass) => {
        const matchesName =
            normalizedSearch === '' ||
            pass.name.toLocaleLowerCase().includes(normalizedSearch);
        const matchesLabels = selectedLabelIds.value.every((labelId) =>
            pass.labels.some((label) => label.id === labelId),
        );

        return matchesName && matchesLabels;
    });
});

const selectedLabelCount = computed(() => selectedLabelIds.value.length);
const labelFilterLabel = computed(() =>
    selectedLabelCount.value === 0
        ? trans('credentials.passes.filters.labels')
        : trans('credentials.passes.filters.labels_selected', {
              count: selectedLabelCount.value,
          }),
);

const clearLabelFilters = () => {
    selectedLabelIds.value = [];
};

const closeLabelFilter = (event) => {
    if (!labelFilter.value?.contains(event.target)) {
        isLabelFilterOpen.value = false;
    }
};

onMounted(() => {
    document.addEventListener('pointerdown', closeLabelFilter);
});

onUnmounted(() => {
    document.removeEventListener('pointerdown', closeLabelFilter);
});
</script>

<template>
    <AppLayout
        :title="$t('credentials.passes.title')"
        :breadcrumbs="breadcrumbs"
    >
        <div class="w-full">
            <div class="mb-3 flex flex-wrap items-start justify-between gap-4">
                <div>
                    <h1 class="m-0 text-2xl font-bold tracking-tight">
                        {{ $t('credentials.passes.title') }}
                    </h1>
                    <p class="mt-1 mb-0 text-sm text-muted">
                        {{ lead }}
                    </p>
                </div>
                <Button
                    v-if="canWrite"
                    href="/credentials/passes/create"
                    variant="primary"
                    class="min-h-10"
                >
                    <Icon
                        :name="['fas', 'plus']"
                        size="sm"
                    />
                    {{ $t('credentials.passes.create') }}
                </Button>
            </div>

            <div class="mb-3 flex flex-wrap items-center gap-3">
                <div class="relative w-full max-w-[420px]">
                    <Icon
                        :name="['fas', 'magnifying-glass']"
                        size="sm"
                        class="pointer-events-none absolute top-1/2 left-3 -translate-y-1/2 text-muted"
                    />
                    <label
                        class="sr-only"
                        for="pass-search"
                    >
                        {{ $t('credentials.passes.filters.search') }}
                    </label>
                    <Input
                        id="pass-search"
                        v-model="search"
                        type="search"
                        :placeholder="
                            $t('credentials.passes.filters.search_placeholder')
                        "
                        class="pl-9"
                    />
                </div>

                <div
                    ref="labelFilter"
                    class="relative"
                >
                    <Button
                        type="button"
                        variant="outline"
                        :aria-expanded="isLabelFilterOpen"
                        aria-haspopup="dialog"
                        @click="isLabelFilterOpen = !isLabelFilterOpen"
                    >
                        {{ labelFilterLabel }}
                        <Icon
                            :name="['fas', 'chevron-down']"
                            size="sm"
                            :class="isLabelFilterOpen ? 'rotate-180' : ''"
                        />
                    </Button>

                    <div
                        v-if="isLabelFilterOpen"
                        class="absolute z-20 mt-2 w-72 rounded-xl border border-line bg-ground p-3 shadow-toast"
                        role="dialog"
                        :aria-label="$t('credentials.passes.filters.labels')"
                    >
                        <p class="m-0 text-xs text-muted">
                            {{ $t('credentials.passes.filters.labels_hint') }}
                        </p>
                        <div class="mt-3 space-y-3">
                            <Checkbox
                                v-for="label in props.labels"
                                :key="label.id"
                                v-model="selectedLabelIds"
                                :value="label.id"
                            >
                                <Tag
                                    :name="label.name"
                                    :color="label.color"
                                />
                            </Checkbox>
                        </div>
                        <div class="mt-3 border-t border-line pt-3 text-right">
                            <button
                                type="button"
                                class="text-xs font-semibold text-secondary hover:underline focus-visible:ring-[3px] focus-visible:ring-primary/35 focus-visible:outline-none"
                                @click="clearLabelFilters"
                            >
                                {{ $t('credentials.passes.filters.clear') }}
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <Table>
                <TableHeader>
                    <TableRow variant="header">
                        <TableHead>{{
                            $t('credentials.passes.table.name')
                        }}</TableHead>
                        <TableHead>{{
                            $t('credentials.passes.table.usage')
                        }}</TableHead>
                        <TableHead>{{
                            $t('credentials.passes.table.labels')
                        }}</TableHead>
                        <TableHead class="w-24 text-right">
                            {{ $t('credentials.passes.table.actions') }}
                        </TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableRow
                        v-for="pass in filteredPasses"
                        :key="pass.id"
                    >
                        <TableCell class="font-semibold">{{
                            pass.name
                        }}</TableCell>
                        <TableCell class="text-muted">{{
                            usageFor(pass)
                        }}</TableCell>
                        <TableCell>
                            <div class="flex flex-wrap gap-1.5">
                                <Tag
                                    v-for="label in pass.labels"
                                    :key="label.id"
                                    :name="label.name"
                                    :color="label.color"
                                />
                            </div>
                        </TableCell>
                        <TableCell class="text-right">
                            <div class="flex justify-end gap-2">
                                <IconButton
                                    :icon="['fas', 'pen']"
                                    :label="
                                        $t('credentials.passes.edit', {
                                            pass: pass.name,
                                        })
                                    "
                                    tone="edit"
                                    :href="`/credentials/passes/${pass.id}/edit`"
                                />
                            </div>
                        </TableCell>
                    </TableRow>
                    <TableRow v-if="filteredPasses.length === 0">
                        <TableCell
                            colspan="4"
                            class="py-8 text-center text-muted"
                        >
                            {{
                                props.passes.length === 0
                                    ? $t('credentials.passes.empty.description')
                                    : $t('credentials.passes.empty.filtered')
                            }}
                        </TableCell>
                    </TableRow>
                </TableBody>
            </Table>

            <p
                v-if="!canWrite"
                class="mt-3 mb-0 text-xs leading-5 text-muted"
            >
                {{ $t('credentials.passes.read_only') }}
            </p>

            <p class="mt-3 mb-0 text-xs leading-5 text-muted">
                {{ $t('credentials.passes.note') }}
            </p>
        </div>
    </AppLayout>
</template>
