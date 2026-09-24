<script setup>
import { Button } from '../../components/ui/button';
import { Card } from '../../components/ui/card';
import { Dialog } from '../../components/ui/dialog';
import { FormField } from '../../components/ui/form-field';
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
import { useFlashToast } from '../../composables/useFlashToast';
import { fieldError, toastFormErrors } from '../../lib/fieldError';
import AppLayout from '../../layouts/AppLayout.vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { trans } from 'laravel-vue-i18n';

const props = defineProps({
    event: { type: Object, required: true },
    groups: { type: Array, default: () => [] },
    members: { type: Object, required: true },
    canManage: { type: Boolean, default: false },
});

const { showError, showFormError, showSuccess } = useFlashToast();
const showAdd = ref(false);
const editingId = ref(null);
const deleting = ref(null);
const deleteBusy = ref(false);
const assigningId = ref(null);

const addForm = useForm({ name: '' });
const editForm = useForm({ name: '' });

const canWrite = computed(() => props.canManage && !props.event.is_locked);
const breadcrumbs = computed(() => [
    { label: trans('app.name'), href: '/dashboard' },
    { label: trans('team.title'), href: '/team/advancement' },
    { label: trans('nav.team.configure') },
]);

const submitAdd = () => {
    addForm.post(`/team/events/${props.event.id}/groups`, {
        preserveScroll: true,
        onSuccess: () => {
            addForm.reset();
            showAdd.value = false;
            showSuccess(trans('team.configure.groups.toast.created'));
        },
        onError: (errors) =>
            toastFormErrors(addForm, errors, { showError, showFormError }),
    });
};

const startEdit = (group) => {
    editingId.value = group.id;
    editForm.name = group.name;
    editForm.clearErrors();
};

const submitEdit = (group) => {
    editForm.put(`/team/events/${props.event.id}/groups/${group.id}`, {
        preserveScroll: true,
        onSuccess: () => {
            editingId.value = null;
            showSuccess(trans('team.configure.groups.toast.updated'));
        },
        onError: (errors) =>
            toastFormErrors(editForm, errors, { showError, showFormError }),
    });
};

const confirmDelete = () => {
    const group = deleting.value;
    if (!group || !canWrite.value) return;

    deleteBusy.value = true;
    router.delete(`/team/events/${props.event.id}/groups/${group.id}`, {
        preserveScroll: true,
        onSuccess: () => {
            deleting.value = null;
            showSuccess(trans('team.configure.groups.toast.deleted'));
        },
        onError: (errors) => showFormError(errors),
        onFinish: () => {
            deleteBusy.value = false;
        },
    });
};

const assignGroup = (member, value) => {
    if (!canWrite.value) return;

    assigningId.value = member.id;
    router.put(
        `/team/events/${props.event.id}/members/${member.id}/group`,
        { group_id: value === '' ? null : Number(value) },
        {
            preserveScroll: true,
            onSuccess: () =>
                showSuccess(trans('team.configure.members.toast.assigned')),
            onError: (errors) => showFormError(errors),
            onFinish: () => {
                assigningId.value = null;
            },
        },
    );
};

const deleteDescription = computed(() =>
    deleting.value
        ? trans('team.configure.groups.delete_body', {
              name: deleting.value.name,
          })
        : '',
);

const paginationLabel = (label) =>
    label.replace('&laquo;', '‹').replace('&raquo;', '›');
</script>

<template>
    <AppLayout
        :title="$t('team.configure.title')"
        :breadcrumbs="breadcrumbs"
    >
        <div class="container mx-auto max-w-5xl">
            <header class="mb-6">
                <h1 class="m-0 text-2xl font-bold">
                    {{ $t('team.configure.title') }}
                </h1>
                <p class="mt-2 max-w-3xl text-sm leading-relaxed text-muted">
                    {{ $t('team.configure.lead', { event: event.name }) }}
                </p>
                <p class="mt-1 max-w-3xl text-sm leading-relaxed text-muted">
                    {{ $t('team.configure.distinction') }}
                </p>
            </header>

            <Card
                v-if="event.is_locked"
                class="mb-6 border-warning/50 bg-warning/10 p-4 text-sm"
            >
                {{ $t('team.configure.locked') }}
            </Card>

            <section aria-labelledby="groups-heading">
                <div class="mb-4 flex items-start justify-between gap-4">
                    <div>
                        <h2
                            id="groups-heading"
                            class="m-0 text-lg font-bold"
                        >
                            {{ $t('team.configure.groups.title') }}
                        </h2>
                        <p class="mt-1 text-sm text-muted">
                            {{ $t('team.configure.groups.lead') }}
                        </p>
                    </div>
                    <Button
                        v-if="canWrite"
                        type="button"
                        variant="primary"
                        size="sm"
                        :disabled="showAdd"
                        @click="showAdd = true"
                    >
                        <Icon
                            :name="['fas', 'plus']"
                            size="sm"
                            class="mr-1.5"
                        />
                        {{ $t('team.configure.groups.add') }}
                    </Button>
                </div>

                <Card
                    v-if="showAdd && canWrite"
                    class="mb-3 p-4"
                >
                    <form @submit.prevent="submitAdd">
                        <FormField
                            :label="$t('team.configure.groups.name')"
                            :error="fieldError(addForm, 'name')"
                            required
                            class="mb-4"
                        >
                            <template #default="{ id, invalid }">
                                <Input
                                    :id="id"
                                    v-model="addForm.name"
                                    type="text"
                                    :placeholder="
                                        $t(
                                            'team.configure.groups.name_placeholder',
                                        )
                                    "
                                    :invalid="invalid"
                                    autocomplete="off"
                                />
                            </template>
                        </FormField>
                        <div class="flex justify-end gap-3">
                            <Button
                                type="button"
                                variant="outline"
                                @click="showAdd = false"
                            >
                                {{ $t('setup.actions.cancel') }}
                            </Button>
                            <Button
                                type="submit"
                                variant="primary"
                                :loading="addForm.processing"
                                :disabled="addForm.processing"
                            >
                                {{ $t('setup.actions.add') }}
                            </Button>
                        </div>
                    </form>
                </Card>

                <div
                    v-if="groups.length"
                    class="grid gap-3 sm:grid-cols-2"
                >
                    <Card
                        v-for="group in groups"
                        :key="group.id"
                        class="p-4"
                    >
                        <form
                            v-if="editingId === group.id && canWrite"
                            @submit.prevent="submitEdit(group)"
                        >
                            <FormField
                                :label="$t('team.configure.groups.name')"
                                :error="fieldError(editForm, 'name')"
                                required
                                class="mb-3"
                            >
                                <template #default="{ id, invalid }">
                                    <Input
                                        :id="id"
                                        v-model="editForm.name"
                                        type="text"
                                        :invalid="invalid"
                                    />
                                </template>
                            </FormField>
                            <div class="flex justify-end gap-2">
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    @click="editingId = null"
                                >
                                    {{ $t('setup.actions.cancel') }}
                                </Button>
                                <Button
                                    type="submit"
                                    variant="primary"
                                    size="sm"
                                    :loading="editForm.processing"
                                >
                                    {{ $t('setup.actions.save') }}
                                </Button>
                            </div>
                        </form>
                        <div
                            v-else
                            class="flex items-center justify-between gap-3"
                        >
                            <div class="min-w-0">
                                <strong class="block truncate">{{
                                    group.name
                                }}</strong>
                                <span class="mt-0.5 block text-xs text-muted">
                                    {{
                                        $t(
                                            'team.configure.groups.member_count',
                                            {
                                                count: group.team_engagements_count,
                                            },
                                            group.team_engagements_count,
                                        )
                                    }}
                                </span>
                            </div>
                            <div
                                v-if="canWrite"
                                class="flex shrink-0 gap-1"
                            >
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="icon"
                                    :aria-label="
                                        $t('team.configure.groups.edit', {
                                            name: group.name,
                                        })
                                    "
                                    @click="startEdit(group)"
                                >
                                    <Icon
                                        :name="['fas', 'pencil']"
                                        size="sm"
                                    />
                                </Button>
                                <Button
                                    type="button"
                                    variant="outline-danger"
                                    size="icon"
                                    :aria-label="
                                        $t('team.configure.groups.delete', {
                                            name: group.name,
                                        })
                                    "
                                    @click="deleting = group"
                                >
                                    <Icon
                                        :name="['fas', 'trash-can']"
                                        size="sm"
                                    />
                                </Button>
                            </div>
                        </div>
                    </Card>
                </div>
                <div
                    v-else
                    class="rounded-lg border border-dashed border-line px-4 py-8 text-center text-sm text-muted"
                >
                    {{ $t('team.configure.groups.empty') }}
                </div>
            </section>

            <section
                class="mt-10"
                aria-labelledby="members-heading"
            >
                <div class="mb-4">
                    <h2
                        id="members-heading"
                        class="m-0 text-lg font-bold"
                    >
                        {{ $t('team.configure.members.title') }}
                    </h2>
                    <p class="mt-1 text-sm text-muted">
                        {{ $t('team.configure.members.lead') }}
                    </p>
                </div>

                <div class="overflow-x-auto rounded-lg border border-line">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>
                                    {{ $t('team.configure.members.member') }}
                                </TableHead>
                                <TableHead>
                                    {{ $t('team.configure.members.group') }}
                                </TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableRow
                                v-for="member in members.data"
                                :key="member.id"
                            >
                                <TableCell>
                                    <strong class="block text-sm">{{
                                        member.person.name
                                    }}</strong>
                                    <span class="text-xs text-muted">{{
                                        member.person.email
                                    }}</span>
                                </TableCell>
                                <TableCell class="w-72">
                                    <Select
                                        :model-value="member.group_id ?? ''"
                                        :disabled="
                                            !canWrite ||
                                            assigningId === member.id ||
                                            groups.length === 0
                                        "
                                        :aria-label="
                                            $t(
                                                'team.configure.members.group_for',
                                                { name: member.person.name },
                                            )
                                        "
                                        @update:model-value="
                                            (value) =>
                                                assignGroup(member, value)
                                        "
                                    >
                                        <option value="">
                                            {{
                                                $t(
                                                    'team.configure.members.unassigned',
                                                )
                                            }}
                                        </option>
                                        <option
                                            v-for="group in groups"
                                            :key="group.id"
                                            :value="group.id"
                                        >
                                            {{ group.name }}
                                        </option>
                                    </Select>
                                </TableCell>
                            </TableRow>
                            <TableRow v-if="members.data.length === 0">
                                <TableCell
                                    colspan="2"
                                    class="py-8 text-center text-muted"
                                >
                                    {{ $t('team.configure.members.empty') }}
                                </TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>
                </div>

                <nav
                    v-if="members.last_page > 1"
                    class="mt-4 flex flex-wrap justify-center gap-2"
                    :aria-label="$t('team.configure.members.pagination')"
                >
                    <Button
                        v-for="link in members.links"
                        :key="link.label"
                        :as="link.url ? Link : 'span'"
                        :href="link.url ?? ''"
                        :variant="link.active ? 'primary' : 'outline'"
                        size="sm"
                        :disabled="!link.url"
                        preserve-scroll
                    >
                        {{ paginationLabel(link.label) }}
                    </Button>
                </nav>
            </section>
        </div>

        <Dialog
            :open="Boolean(deleting)"
            :title="$t('team.configure.groups.delete_title')"
            :description="deleteDescription"
            :confirm-label="$t('setup.actions.delete')"
            :cancel-label="$t('setup.actions.cancel')"
            confirm-variant="danger"
            :busy="deleteBusy"
            @update:open="(open) => !open && (deleting = null)"
            @confirm="confirmDelete"
            @cancel="deleting = null"
        />
    </AppLayout>
</template>
