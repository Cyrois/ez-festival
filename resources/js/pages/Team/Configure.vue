<script setup>
import { Button } from '../../components/ui/button';
import { Dialog } from '../../components/ui/dialog';
import { FormField } from '../../components/ui/form-field';
import { Icon } from '../../components/ui/icon';
import { Input } from '../../components/ui/input';
import { Textarea } from '../../components/ui/textarea';
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
import { computed, onUnmounted, ref, watch } from 'vue';
import { trans } from 'laravel-vue-i18n';

const props = defineProps({
    event: { type: Object, required: true },
    groups: { type: Object, required: true },
    filters: { type: Object, required: true },
    canManage: { type: Boolean, default: false },
});

const { showError, showFormError, showSuccess } = useFlashToast();
const search = ref(props.filters.search);
const editorOpen = ref(false);
const editing = ref(null);
const deleting = ref(null);
const deleteBusy = ref(false);
const groupForm = useForm({ name: '', description: '' });
let searchTimer;

const canWrite = computed(() => props.canManage && !props.event.is_locked);
const breadcrumbs = computed(() => [
    { label: trans('app.name'), href: '/dashboard' },
    { label: trans('team.title'), href: '/team/advancement' },
    { label: trans('nav.team.configure') },
]);
const editorTitle = computed(() =>
    editing.value
        ? trans('team.configure.groups.edit_title')
        : trans('team.configure.groups.create_title'),
);
const editorConfirmLabel = computed(() =>
    editing.value ? trans('setup.actions.save') : trans('setup.actions.add'),
);
const deleteDescription = computed(() =>
    deleting.value
        ? trans('team.configure.groups.delete_body', {
              name: deleting.value.name,
          })
        : '',
);

const openCreate = () => {
    editing.value = null;
    groupForm.reset();
    groupForm.clearErrors();
    editorOpen.value = true;
};

const openEdit = (group) => {
    editing.value = group;
    groupForm.name = group.name;
    groupForm.description = group.description ?? '';
    groupForm.clearErrors();
    editorOpen.value = true;
};

const closeEditor = (force = false) => {
    if (groupForm.processing && !force) return;

    editorOpen.value = false;
    editing.value = null;
    groupForm.reset();
    groupForm.clearErrors();
};

const submitGroup = () => {
    const options = {
        preserveScroll: true,
        onSuccess: () => {
            const message = editing.value
                ? trans('team.configure.groups.toast.updated')
                : trans('team.configure.groups.toast.created');
            closeEditor(true);
            showSuccess(message);
        },
        onError: (errors) =>
            toastFormErrors(groupForm, errors, { showError, showFormError }),
    };

    if (editing.value) {
        groupForm.put(
            `/team/events/${props.event.id}/groups/${editing.value.id}`,
            options,
        );
        return;
    }

    groupForm.post(`/team/events/${props.event.id}/groups`, options);
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

const paginationLabel = (label) =>
    label.replace('&laquo;', '‹').replace('&raquo;', '›');

watch(search, (value) => {
    window.clearTimeout(searchTimer);
    searchTimer = window.setTimeout(() => {
        router.get(
            '/team/configure',
            { search: value },
            {
                preserveState: true,
                preserveScroll: true,
                replace: true,
            },
        );
    }, 300);
});

watch(
    () => props.filters.search,
    (value) => {
        search.value = value;
    },
);

onUnmounted(() => window.clearTimeout(searchTimer));
</script>

<template>
    <AppLayout
        :title="$t('team.configure.title')"
        :breadcrumbs="breadcrumbs"
    >
        <div class="container mx-auto max-w-6xl">
            <header class="mb-6">
                <h1 class="m-0 text-2xl font-bold tracking-tight">
                    {{ $t('team.configure.title') }}
                </h1>
                <p class="mt-1 mb-0 text-sm text-muted">
                    {{ $t('team.configure.lead', { event: event.name }) }}
                </p>
            </header>

            <p
                v-if="event.is_locked"
                class="mb-4 flex items-center gap-2 rounded-lg border border-warning/20 bg-warning/10 p-3 text-sm text-charcoal"
                role="status"
            >
                <Icon :name="['fas', 'lock']" />
                {{ $t('team.configure.locked') }}
            </p>

            <div
                class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
            >
                <form
                    class="relative w-full sm:w-80"
                    role="search"
                    @submit.prevent
                >
                    <Icon
                        :name="['fas', 'magnifying-glass']"
                        size="sm"
                        class="pointer-events-none absolute top-1/2 left-3 -translate-y-1/2 text-muted"
                    />
                    <Input
                        v-model="search"
                        type="search"
                        class="pl-9"
                        :placeholder="$t('team.configure.groups.search')"
                        :aria-label="$t('team.configure.groups.search')"
                    />
                </form>

                <Button
                    v-if="canWrite"
                    type="button"
                    variant="primary"
                    class="w-full sm:w-auto"
                    @click="openCreate"
                >
                    <Icon
                        :name="['fas', 'plus']"
                        size="sm"
                        class="mr-1.5"
                    />
                    {{ $t('team.configure.groups.add') }}
                </Button>
            </div>

            <div
                class="overflow-x-auto rounded-lg border border-line bg-ground"
            >
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>
                                {{ $t('team.configure.groups.columns.name') }}
                            </TableHead>
                            <TableHead>
                                {{
                                    $t(
                                        'team.configure.groups.columns.description',
                                    )
                                }}
                            </TableHead>
                            <TableHead>
                                {{
                                    $t('team.configure.groups.columns.members')
                                }}
                            </TableHead>
                            <TableHead class="w-36">
                                {{
                                    $t('team.configure.groups.columns.actions')
                                }}
                            </TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow
                            v-for="group in groups.data"
                            :key="group.id"
                        >
                            <TableCell>
                                <strong>{{ group.name }}</strong>
                            </TableCell>
                            <TableCell class="text-muted">
                                {{
                                    group.description ||
                                    $t('team.configure.groups.no_description')
                                }}
                            </TableCell>
                            <TableCell>
                                {{
                                    $t(
                                        'team.configure.groups.member_count',
                                        {
                                            count: group.team_engagements_count,
                                        },
                                        group.team_engagements_count,
                                    )
                                }}
                            </TableCell>
                            <TableCell>
                                <div
                                    v-if="canWrite"
                                    class="flex items-center gap-2"
                                >
                                    <Button
                                        type="button"
                                        variant="outline-secondary"
                                        size="icon"
                                        :aria-label="
                                            $t('team.configure.groups.edit', {
                                                name: group.name,
                                            })
                                        "
                                        @click="openEdit(group)"
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
                                <span
                                    v-else
                                    class="text-sm text-muted"
                                >
                                    {{ $t('team.configure.groups.read_only') }}
                                </span>
                            </TableCell>
                        </TableRow>
                        <TableRow v-if="groups.data.length === 0">
                            <TableCell
                                colspan="4"
                                class="py-10 text-center text-muted"
                            >
                                {{
                                    search
                                        ? $t('team.configure.groups.no_results')
                                        : $t('team.configure.groups.empty')
                                }}
                            </TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
            </div>

            <nav
                v-if="groups.last_page > 1"
                class="mt-4 flex flex-wrap justify-center gap-2"
                :aria-label="$t('team.configure.groups.pagination')"
            >
                <Button
                    v-for="link in groups.links"
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
        </div>

        <Dialog
            :open="editorOpen"
            :title="editorTitle"
            :confirm-label="editorConfirmLabel"
            :cancel-label="$t('setup.actions.cancel')"
            confirm-variant="primary"
            :busy="groupForm.processing"
            @update:open="(open) => !open && closeEditor()"
            @confirm="submitGroup"
            @cancel="closeEditor"
        >
            <form
                class="mt-4"
                @submit.prevent="submitGroup"
            >
                <FormField
                    :label="$t('team.configure.groups.name')"
                    :error="fieldError(groupForm, 'name')"
                    required
                >
                    <template #default="{ id, invalid }">
                        <Input
                            :id="id"
                            v-model="groupForm.name"
                            type="text"
                            :placeholder="
                                $t('team.configure.groups.name_placeholder')
                            "
                            :invalid="invalid"
                            autocomplete="off"
                        />
                    </template>
                </FormField>
                <FormField
                    :label="$t('team.configure.groups.description')"
                    :error="fieldError(groupForm, 'description')"
                    class="mt-4"
                >
                    <template #default="{ id, invalid }">
                        <Textarea
                            :id="id"
                            v-model="groupForm.description"
                            :placeholder="
                                $t(
                                    'team.configure.groups.description_placeholder',
                                )
                            "
                            :invalid="invalid"
                            rows="3"
                        />
                    </template>
                </FormField>
            </form>
        </Dialog>

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
