<script setup>
import AppLayout from '../../layouts/AppLayout.vue';
import { Button } from '../../components/ui/button';
import { FormField } from '../../components/ui/form-field';
import { Icon } from '../../components/ui/icon';
import { IconButton } from '../../components/ui/icon-button';
import { Input } from '../../components/ui/input';
import { Popup } from '../../components/ui/popup';
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
import { Textarea } from '../../components/ui/textarea';
import { useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { trans } from 'laravel-vue-i18n';

const props = defineProps({
    items: { type: Array, default: () => [] },
    labels: { type: Array, default: () => [] },
    canWrite: { type: Boolean, default: false },
});

const page = usePage();
const event = computed(() => page.props.activeEvent ?? {});
const breadcrumbs = computed(() => [
    { label: trans('app.name'), href: '/dashboard' },
    { label: trans('nav.credentials'), href: '/credentials/passes' },
    { label: trans('nav.credentials.entitlements') },
]);
const addOpen = ref(false);
const adjustOpen = ref(false);
const selectedItem = ref(null);
const selectedLabelIds = ref([]);
const search = ref('');
const newLabelName = ref('');
const createForm = useForm({
    name: '',
    opening_balance: 0,
    label_ids: [],
    new_labels: [],
});
const adjustForm = useForm({ direction: 'add', quantity: 1, reason: '' });
const directionOptions = computed(() => [
    { value: 'add', label: trans('credentials.entitlements.adjust.add') },
    { value: 'remove', label: trans('credentials.entitlements.adjust.remove') },
]);
const adjustmentConfirmation = computed(() =>
    trans('credentials.entitlements.adjust.confirmation', {
        direction: trans(
            `credentials.entitlements.adjust.${adjustForm.direction}`,
        ),
        quantity: adjustForm.quantity || 0,
    }),
);
const filteredItems = computed(() => {
    const query = search.value.trim().toLocaleLowerCase();

    return props.items.filter((item) => {
        const matchesText =
            query === '' ||
            [item.name, ...item.labels.map((label) => label.name)]
                .join(' ')
                .toLocaleLowerCase()
                .includes(query);
        const matchesLabels = selectedLabelIds.value.every((labelId) =>
            item.labels.some((label) => label.id === labelId),
        );

        return matchesText && matchesLabels;
    });
});
const hasFilters = computed(
    () => search.value.trim() !== '' || selectedLabelIds.value.length > 0,
);
const toggleFilter = (id) => {
    selectedLabelIds.value = selectedLabelIds.value.includes(id)
        ? selectedLabelIds.value.filter((value) => value !== id)
        : [...selectedLabelIds.value, id];
};
const clearFilters = () => {
    search.value = '';
    selectedLabelIds.value = [];
};
const toggleFormLabel = (form, id) => {
    form.label_ids = form.label_ids.includes(id)
        ? form.label_ids.filter((value) => value !== id)
        : [...form.label_ids, id];
};
const addNewLabel = (form) => {
    const name = newLabelName.value.trim();
    if (!name) return;
    form.new_labels.push({ name, color: 'primary' });
    newLabelName.value = '';
};

const openAdd = () => {
    createForm.reset();
    createForm.clearErrors();
    createForm.opening_balance = 0;
    newLabelName.value = '';
    addOpen.value = true;
};

const openAdjust = (item) => {
    selectedItem.value = item;
    adjustForm.reset();
    adjustForm.clearErrors();
    adjustForm.direction = 'add';
    adjustForm.quantity = 1;
    adjustOpen.value = true;
};

const createItem = () => {
    createForm.post(`/events/${event.value.id}/credentials/entitlements`, {
        preserveScroll: true,
        onSuccess: () => {
            addOpen.value = false;
        },
    });
};

const adjustItem = () => {
    if (!selectedItem.value) {
        return;
    }

    adjustForm.post(
        `/events/${event.value.id}/credentials/entitlements/${selectedItem.value.id}/adjustments`,
        {
            preserveScroll: true,
            onSuccess: () => {
                adjustOpen.value = false;
            },
        },
    );
};
</script>

<template>
    <AppLayout
        :title="$t('credentials.entitlements.title')"
        :breadcrumbs="breadcrumbs"
    >
        <div class="container mx-auto">
            <div class="mb-7">
                <h1 class="m-0 text-2xl font-bold tracking-tight">
                    {{ $t('credentials.entitlements.title') }}
                </h1>
                <p class="mt-1 mb-0 max-w-3xl text-sm text-muted">
                    {{ $t('credentials.entitlements.lead') }}
                </p>
            </div>

            <section aria-labelledby="entitlement-items-heading">
                <div
                    class="mb-3 flex flex-wrap items-start justify-between gap-4"
                >
                    <div>
                        <h2
                            id="entitlement-items-heading"
                            class="m-0 text-lg font-bold"
                        >
                            {{ $t('credentials.entitlements.items.title') }}
                        </h2>
                        <p class="mt-1 mb-0 max-w-3xl text-sm text-muted">
                            {{ $t('credentials.entitlements.items.lead') }}
                        </p>
                    </div>
                    <Button
                        v-if="canWrite"
                        type="button"
                        class="min-h-10"
                        @click="openAdd"
                    >
                        <Icon
                            :name="['fas', 'plus']"
                            size="sm"
                        />
                        {{ $t('credentials.entitlements.actions.add') }}
                    </Button>
                </div>

                <div class="mb-3 flex flex-wrap items-center gap-2">
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
                            id="entitlement-search"
                            v-model="search"
                            type="search"
                            class="min-h-11 pl-9"
                            :aria-label="
                                $t('credentials.entitlements.items.search')
                            "
                            :placeholder="
                                $t('credentials.entitlements.items.search')
                            "
                        />
                    </form>
                    <div
                        v-if="labels.length"
                        class="flex flex-wrap gap-2"
                    >
                        <button
                            v-for="label in labels"
                            :key="label.id"
                            type="button"
                            class="rounded-full focus-visible:ring-[3px] focus-visible:ring-primary/35 focus-visible:outline-none"
                            :class="
                                selectedLabelIds.includes(label.id)
                                    ? 'ring-2 ring-primary ring-offset-2'
                                    : ''
                            "
                            :aria-pressed="selectedLabelIds.includes(label.id)"
                            @click="toggleFilter(label.id)"
                        >
                            <Tag
                                :name="label.name"
                                :color="label.color"
                                class="cursor-pointer"
                            />
                        </button>
                    </div>
                    <Button
                        v-if="hasFilters"
                        type="button"
                        variant="ghost"
                        @click="clearFilters"
                        >{{
                            $t('credentials.entitlements.items.clear_filters')
                        }}</Button
                    >
                </div>

                <Table>
                    <TableHeader>
                        <TableRow variant="header">
                            <TableHead>{{
                                $t('credentials.entitlements.items.table.name')
                            }}</TableHead>
                            <TableHead>{{
                                $t(
                                    'credentials.entitlements.items.table.labels',
                                )
                            }}</TableHead>
                            <TableHead class="w-32 text-right">{{
                                $t(
                                    'credentials.entitlements.items.table.balance',
                                )
                            }}</TableHead>
                            <TableHead class="w-36 text-right">{{
                                $t(
                                    'credentials.entitlements.items.table.actions',
                                )
                            }}</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow
                            v-for="item in filteredItems"
                            :key="item.id"
                        >
                            <TableCell class="font-semibold">
                                <a
                                    :href="`/credentials/entitlements/${item.id}`"
                                    class="text-secondary hover:underline"
                                >
                                    {{ item.name }}
                                </a>
                            </TableCell>
                            <TableCell>
                                <div class="flex flex-wrap gap-1.5">
                                    <Tag
                                        v-for="label in item.labels"
                                        :key="label.id"
                                        :name="label.name"
                                        :color="label.color"
                                    />
                                </div>
                            </TableCell>
                            <TableCell
                                class="text-right font-semibold tabular-nums"
                                >{{ item.balance }}</TableCell
                            >
                            <TableCell class="text-right">
                                <div
                                    v-if="canWrite"
                                    class="flex justify-end gap-2"
                                >
                                    <IconButton
                                        :href="`/credentials/entitlements/${item.id}/edit`"
                                        :icon="['fas', 'pencil']"
                                        :label="
                                            $t(
                                                'credentials.entitlements.actions.edit',
                                                { item: item.name },
                                            )
                                        "
                                        tone="edit"
                                    />
                                    <IconButton
                                        :icon="['fas', 'scale-balanced']"
                                        :label="
                                            $t(
                                                'credentials.entitlements.actions.adjust',
                                            )
                                        "
                                        tone="edit"
                                        @click="openAdjust(item)"
                                    />
                                </div>
                            </TableCell>
                        </TableRow>
                        <TableRow v-if="filteredItems.length === 0">
                            <TableCell
                                colspan="4"
                                class="py-8 text-center text-muted"
                            >
                                {{
                                    hasFilters
                                        ? $t(
                                              'credentials.entitlements.items.empty_filtered',
                                          )
                                        : $t(
                                              'credentials.entitlements.items.empty',
                                          )
                                }}
                            </TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
            </section>

            <p
                v-if="!canWrite"
                class="mt-3 mb-0 text-sm text-muted"
            >
                {{ $t('credentials.entitlements.read_only') }}
            </p>
        </div>

        <Popup
            v-model:open="addOpen"
            :title="$t('credentials.entitlements.add.title')"
            :description="$t('credentials.entitlements.add.lead')"
            :accept-label="$t('credentials.entitlements.actions.save')"
            :cancel-label="$t('ui.dialog.cancel')"
            :busy="createForm.processing"
            @accept="createItem"
        >
            <div class="mt-5 space-y-4">
                <FormField
                    :label="$t('credentials.entitlements.fields.name')"
                    :error="createForm.errors.name"
                    required
                >
                    <template #default="{ id, invalid }">
                        <Input
                            :id="id"
                            v-model="createForm.name"
                            :invalid="invalid"
                            :placeholder="
                                $t(
                                    'credentials.entitlements.fields.name_placeholder',
                                )
                            "
                            autocomplete="off"
                        />
                    </template>
                </FormField>
                <div>
                    <p class="m-0 text-xs font-bold text-charcoal">
                        {{ $t('credentials.entitlements.labels.title') }}
                    </p>
                    <div class="mt-2 flex flex-wrap gap-2">
                        <button
                            v-for="label in labels"
                            :key="label.id"
                            type="button"
                            class="rounded-full focus-visible:ring-[3px] focus-visible:ring-primary/35 focus-visible:outline-none"
                            :class="
                                createForm.label_ids.includes(label.id)
                                    ? 'ring-2 ring-primary ring-offset-2'
                                    : ''
                            "
                            @click="toggleFormLabel(createForm, label.id)"
                        >
                            <Tag
                                :name="label.name"
                                :color="label.color"
                            />
                        </button>
                        <Tag
                            v-for="label in createForm.new_labels"
                            :key="label.name"
                            :name="label.name"
                            :color="label.color"
                        />
                    </div>
                    <div class="mt-2 flex gap-2">
                        <Input
                            v-model="newLabelName"
                            :placeholder="
                                $t(
                                    'credentials.entitlements.labels.placeholder',
                                )
                            "
                        />
                        <Button
                            type="button"
                            variant="outline"
                            @click="addNewLabel(createForm)"
                            >{{
                                $t('credentials.entitlements.labels.add')
                            }}</Button
                        >
                    </div>
                </div>
                <FormField
                    :label="
                        $t('credentials.entitlements.fields.opening_balance')
                    "
                    :error="createForm.errors.opening_balance"
                    :hint="
                        $t(
                            'credentials.entitlements.fields.opening_balance_hint',
                        )
                    "
                    required
                >
                    <template #default="{ id, invalid }">
                        <Input
                            :id="id"
                            v-model="createForm.opening_balance"
                            :invalid="invalid"
                            type="number"
                            min="0"
                            step="1"
                        />
                    </template>
                </FormField>
            </div>
        </Popup>

        <Popup
            v-model:open="adjustOpen"
            :title="$t('credentials.entitlements.adjust.title')"
            :description="
                $t('credentials.entitlements.adjust.lead', {
                    balance: selectedItem?.balance ?? 0,
                })
            "
            :accept-label="
                $t('credentials.entitlements.actions.apply_adjustment')
            "
            :cancel-label="$t('ui.dialog.cancel')"
            :busy="adjustForm.processing"
            @accept="adjustItem"
        >
            <div class="mt-5 space-y-4">
                <p class="m-0 text-sm text-muted">
                    {{ adjustmentConfirmation }}
                </p>
                <FormField
                    :label="$t('credentials.entitlements.fields.direction')"
                    :error="adjustForm.errors.direction"
                    required
                >
                    <template #default="{ id }">
                        <SegmentedControl
                            :id="id"
                            v-model="adjustForm.direction"
                            :options="directionOptions"
                            :aria-label="
                                $t('credentials.entitlements.fields.direction')
                            "
                        />
                    </template>
                </FormField>
                <FormField
                    :label="$t('credentials.entitlements.fields.quantity')"
                    :error="adjustForm.errors.quantity"
                    required
                >
                    <template #default="{ id, invalid }">
                        <Input
                            :id="id"
                            v-model="adjustForm.quantity"
                            :invalid="invalid"
                            type="number"
                            min="1"
                            step="1"
                        />
                    </template>
                </FormField>
                <FormField
                    :label="$t('credentials.entitlements.fields.reason')"
                    :error="adjustForm.errors.reason"
                    required
                >
                    <template #default="{ id, invalid }">
                        <Textarea
                            :id="id"
                            v-model="adjustForm.reason"
                            :invalid="invalid"
                            :placeholder="
                                $t(
                                    'credentials.entitlements.fields.reason_placeholder',
                                )
                            "
                            maxlength="500"
                        />
                    </template>
                </FormField>
            </div>
        </Popup>
    </AppLayout>
</template>
