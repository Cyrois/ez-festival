<script setup>
import AppLayout from '../../layouts/AppLayout.vue';
import { Badge } from '../../components/ui/badge';
import { Button } from '../../components/ui/button';
import { Card } from '../../components/ui/card';
import { EmptyState } from '../../components/ui/empty-state';
import { FormField } from '../../components/ui/form-field';
import { Icon } from '../../components/ui/icon';
import { IconButton } from '../../components/ui/icon-button';
import { Input } from '../../components/ui/input';
import { Popup } from '../../components/ui/popup';
import { Select } from '../../components/ui/select';
import { useFlashToast } from '../../composables/useFlashToast';
import { router, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import { trans } from 'laravel-vue-i18n';

const props = defineProps({
    locations: { type: Array, default: () => [] },
    templates: { type: Array, default: () => [] },
    shiftRoles: { type: Array, default: () => [] },
    canWrite: { type: Boolean, default: false },
    settingsLocationsUrl: { type: String, default: '/settings/locations' },
});

const page = usePage();
const event = computed(() => page.props.activeEvent ?? {});
const { showFormError } = useFlashToast();

const breadcrumbs = computed(() => [
    { label: trans('app.name'), href: '/dashboard' },
    { label: trans('team.title'), href: '/team/advancement' },
    { label: trans('nav.team.configure') },
]);

const selectedLocationId = ref(props.locations[0]?.id ?? null);

watch(
    () => props.locations,
    (locations) => {
        if (
            locations.length > 0 &&
            !locations.some(
                (location) => location.id === selectedLocationId.value,
            )
        ) {
            selectedLocationId.value = locations[0].id;
        }
        if (locations.length === 0) {
            selectedLocationId.value = null;
        }
    },
);

const selectedLocation = computed(() =>
    props.locations.find(
        (location) => location.id === selectedLocationId.value,
    ),
);

const locationTemplates = computed(() =>
    props.templates.filter(
        (template) => template.location_id === selectedLocationId.value,
    ),
);

const templatesCountLabel = (count) =>
    count === 1
        ? trans('team.configure.locations.templates_count_one', { count })
        : trans('team.configure.locations.templates_count_other', { count });

const roleMixSummary = (template) =>
    template.roles
        .map((role) =>
            trans('team.configure.templates.role_line', {
                count: role.headcount,
                name: role.name,
            }),
        )
        .join(' + ');

const templateNeedsLabel = (template) =>
    trans('team.configure.templates.needs_summary', {
        needs: template.needs,
        mix: roleMixSummary(template),
    });

const roleForm = useForm({ name: '' });
const roleModalOpen = ref(false);
const editingRole = ref(null);

const openCreateRole = () => {
    editingRole.value = null;
    roleForm.reset();
    roleForm.clearErrors();
    roleForm.name = '';
    roleModalOpen.value = true;
};

const openEditRole = (role) => {
    editingRole.value = role;
    roleForm.reset();
    roleForm.clearErrors();
    roleForm.name = role.name;
    roleModalOpen.value = true;
};

const submitRole = () => {
    const options = {
        preserveScroll: true,
        onError: showFormError,
        onSuccess: () => {
            roleModalOpen.value = false;
        },
    };

    if (editingRole.value) {
        roleForm.put(
            `/events/${event.value.id}/team/shift-roles/${editingRole.value.id}`,
            options,
        );
        return;
    }

    roleForm.post(`/events/${event.value.id}/team/shift-roles`, options);
};

const deleteRole = (role) => {
    if (!window.confirm(trans('team.configure.shift_roles.confirm_delete'))) {
        return;
    }

    router.delete(`/events/${event.value.id}/team/shift-roles/${role.id}`, {
        preserveScroll: true,
        onError: showFormError,
    });
};

const emptyRoleLine = () => ({
    shift_role_id: props.shiftRoles[0]?.id ?? '',
    headcount: 1,
});

const templateForm = useForm({
    name: '',
    location_id: '',
    roles: [emptyRoleLine()],
});
const templateModalOpen = ref(false);
const editingTemplate = ref(null);

const templateNeedsPreview = computed(() =>
    templateForm.roles.reduce(
        (sum, line) => sum + (Number(line.headcount) || 0),
        0,
    ),
);

const openCreateTemplate = () => {
    editingTemplate.value = null;
    templateForm.reset();
    templateForm.clearErrors();
    templateForm.name = '';
    templateForm.location_id = selectedLocationId.value ?? '';
    templateForm.roles = [emptyRoleLine()];
    templateModalOpen.value = true;
};

const openEditTemplate = (template) => {
    editingTemplate.value = template;
    templateForm.reset();
    templateForm.clearErrors();
    templateForm.name = template.name;
    templateForm.location_id = template.location_id;
    templateForm.roles = template.roles.map((role) => ({
        shift_role_id: role.shift_role_id,
        headcount: role.headcount,
    }));
    templateModalOpen.value = true;
};

const addRoleLine = () => {
    templateForm.roles.push(emptyRoleLine());
};

const removeRoleLine = (index) => {
    if (templateForm.roles.length <= 1) {
        return;
    }
    templateForm.roles.splice(index, 1);
};

const submitTemplate = () => {
    const options = {
        preserveScroll: true,
        onError: showFormError,
        onSuccess: () => {
            templateModalOpen.value = false;
        },
    };

    if (editingTemplate.value) {
        templateForm.put(
            `/events/${event.value.id}/team/shift-templates/${editingTemplate.value.id}`,
            options,
        );
        return;
    }

    templateForm.post(
        `/events/${event.value.id}/team/shift-templates`,
        options,
    );
};

const deleteTemplate = (template) => {
    if (!window.confirm(trans('team.configure.templates.confirm_delete'))) {
        return;
    }

    router.delete(
        `/events/${event.value.id}/team/shift-templates/${template.id}`,
        {
            preserveScroll: true,
            onError: showFormError,
        },
    );
};
</script>

<template>
    <AppLayout
        :title="$t('team.configure.title')"
        :breadcrumbs="breadcrumbs"
    >
        <div class="container mx-auto space-y-10">
            <section>
                <div
                    class="mb-4 flex flex-wrap items-start justify-between gap-4"
                >
                    <div>
                        <h1 class="m-0 text-2xl font-bold tracking-tight">
                            {{ $t('team.configure.title') }}
                        </h1>
                        <p class="mt-1 mb-0 text-sm text-muted">
                            {{ $t('team.configure.lead') }}
                        </p>
                    </div>
                    <div
                        v-if="canWrite"
                        class="flex flex-wrap gap-2"
                    >
                        <Button
                            type="button"
                            variant="outline"
                            class="min-h-10"
                            @click="openCreateRole"
                        >
                            <Icon
                                :name="['fas', 'plus']"
                                size="sm"
                            />
                            {{ $t('team.configure.shift_roles.new') }}
                        </Button>
                        <Button
                            type="button"
                            variant="primary"
                            class="min-h-10"
                            :disabled="
                                locations.length === 0 ||
                                shiftRoles.length === 0
                            "
                            @click="openCreateTemplate"
                        >
                            <Icon
                                :name="['fas', 'plus']"
                                size="sm"
                            />
                            {{ $t('team.configure.templates.new') }}
                        </Button>
                    </div>
                </div>

                <div
                    class="mb-4 rounded-[10px] border border-dashed border-line bg-page px-3.5 py-3 text-xs leading-snug text-muted"
                >
                    {{ $t('team.configure.permission_note') }}
                </div>

                <p
                    v-if="!canWrite"
                    class="mb-4 text-sm text-muted"
                >
                    {{ $t('team.configure.read_only') }}
                </p>

                <h2 class="mb-3 text-lg font-bold text-charcoal">
                    {{ $t('team.configure.sections.shift_config') }}
                </h2>

                <EmptyState
                    v-if="locations.length === 0"
                    :title="$t('team.configure.locations.empty.title')"
                    :description="
                        $t('team.configure.locations.empty.description')
                    "
                >
                    <Button
                        :href="settingsLocationsUrl"
                        variant="primary"
                        class="mt-4 min-h-10"
                    >
                        {{ $t('team.configure.locations.empty.action') }}
                    </Button>
                </EmptyState>

                <div
                    v-else
                    class="flex flex-col gap-5 lg:flex-row lg:items-start"
                >
                    <aside class="w-full shrink-0 lg:w-[220px]">
                        <p
                            class="mb-2 text-[11px] font-bold tracking-wider text-muted uppercase"
                        >
                            {{ $t('team.configure.locations.title') }}
                        </p>
                        <p class="mb-3 text-xs text-muted">
                            {{ $t('team.configure.locations.manage_hint') }}
                        </p>
                        <div class="flex flex-col gap-1.5">
                            <button
                                v-for="location in locations"
                                :key="location.id"
                                type="button"
                                class="block rounded-lg border px-3 py-2.5 text-left text-[13px] font-bold transition-colors"
                                :class="
                                    location.id === selectedLocationId
                                        ? 'border-primary bg-primary-soft text-primary shadow-[0_0_0_1px_var(--color-primary)]'
                                        : 'border-line bg-ground text-charcoal hover:border-primary/40'
                                "
                                @click="selectedLocationId = location.id"
                            >
                                <span class="block">{{ location.name }}</span>
                                <span
                                    class="mt-0.5 block text-[11px] font-normal"
                                    :class="
                                        location.id === selectedLocationId
                                            ? 'text-primary/80'
                                            : 'text-muted'
                                    "
                                >
                                    {{
                                        templatesCountLabel(
                                            location.template_count,
                                        )
                                    }}
                                </span>
                            </button>
                        </div>
                    </aside>

                    <div class="min-w-0 flex-1">
                        <div class="mb-3.5 flex flex-wrap items-center gap-2.5">
                            <strong class="text-sm">{{
                                selectedLocation?.name
                            }}</strong>
                            <span class="flex-1" />
                            <span class="text-xs text-muted">{{
                                $t('team.configure.templates.heading')
                            }}</span>
                        </div>

                        <div
                            v-if="locationTemplates.length === 0"
                            class="rounded-xl border border-dashed border-line bg-page px-4 py-8 text-center text-sm text-muted"
                        >
                            {{ $t('team.configure.templates.empty') }}
                        </div>

                        <div
                            v-else
                            class="grid grid-cols-1 gap-3 md:grid-cols-2"
                        >
                            <Card
                                v-for="template in locationTemplates"
                                :key="template.id"
                                class="!px-4 !py-3.5"
                            >
                                <h3 class="m-0 mb-1.5 text-[15px] font-bold">
                                    {{ template.name }}
                                </h3>
                                <p
                                    class="m-0 mb-2.5 text-xs leading-snug text-muted"
                                >
                                    {{ templateNeedsLabel(template) }}
                                </p>
                                <div class="mb-2.5 flex flex-wrap gap-1.5">
                                    <Badge
                                        v-for="role in template.roles"
                                        :key="`${template.id}-${role.shift_role_id}`"
                                        pill
                                        variant="primary"
                                    >
                                        {{
                                            $t(
                                                'team.configure.templates.role_line',
                                                {
                                                    count: role.headcount,
                                                    name: role.name,
                                                },
                                            )
                                        }}
                                    </Badge>
                                </div>
                                <div
                                    v-if="canWrite"
                                    class="flex items-center gap-2"
                                >
                                    <IconButton
                                        :icon="['fas', 'pen']"
                                        :label="
                                            $t('team.configure.templates.edit')
                                        "
                                        tone="edit"
                                        @click="openEditTemplate(template)"
                                    />
                                    <IconButton
                                        :icon="['fas', 'trash']"
                                        :label="
                                            $t(
                                                'team.configure.templates.delete',
                                            )
                                        "
                                        tone="delete"
                                        @click="deleteTemplate(template)"
                                    />
                                </div>
                            </Card>
                        </div>
                    </div>
                </div>

                <div class="mt-8">
                    <div
                        class="mb-3 flex flex-wrap items-center justify-between gap-2"
                    >
                        <div>
                            <h3 class="m-0 text-base font-bold">
                                {{ $t('team.configure.shift_roles.title') }}
                            </h3>
                            <p class="mt-1 mb-0 text-xs text-muted">
                                {{ $t('team.configure.shift_roles.lead') }}
                            </p>
                        </div>
                    </div>
                    <div
                        v-if="shiftRoles.length === 0"
                        class="rounded-xl border border-dashed border-line bg-page px-4 py-6 text-sm text-muted"
                    >
                        {{ $t('team.configure.shift_roles.empty') }}
                    </div>
                    <div
                        v-else
                        class="flex flex-wrap gap-2"
                    >
                        <div
                            v-for="role in shiftRoles"
                            :key="role.id"
                            class="inline-flex items-center gap-2 rounded-lg border border-line bg-ground px-3 py-2 text-sm font-bold"
                        >
                            <span>{{ role.name }}</span>
                            <template v-if="canWrite">
                                <IconButton
                                    :icon="['fas', 'pen']"
                                    :label="
                                        $t('team.configure.shift_roles.edit')
                                    "
                                    tone="edit"
                                    class="!h-7 !w-7"
                                    @click="openEditRole(role)"
                                />
                                <IconButton
                                    :icon="['fas', 'trash']"
                                    :label="
                                        $t('team.configure.shift_roles.delete')
                                    "
                                    tone="delete"
                                    class="!h-7 !w-7"
                                    @click="deleteRole(role)"
                                />
                            </template>
                        </div>
                    </div>
                </div>
            </section>

            <section
                class="rounded-xl border border-dashed border-line bg-page px-4 py-6 text-sm text-muted"
                aria-label="Groups"
            >
                {{ $t('team.configure.sections.groups_placeholder') }}
            </section>
        </div>

        <Popup
            v-model:open="roleModalOpen"
            :title="
                editingRole
                    ? $t('team.configure.shift_roles.edit')
                    : $t('team.configure.shift_roles.new')
            "
            :accept-label="
                editingRole
                    ? $t('team.configure.shift_roles.actions.save')
                    : $t('team.configure.shift_roles.actions.create')
            "
            :cancel-label="$t('ui.dialog.cancel')"
            :busy="roleForm.processing"
            @accept="submitRole"
        >
            <div class="mt-5 space-y-4">
                <FormField
                    :label="$t('team.configure.shift_roles.fields.name')"
                    :error="roleForm.errors.name"
                    required
                >
                    <template #default="{ id, invalid }">
                        <Input
                            :id="id"
                            v-model="roleForm.name"
                            :invalid="invalid"
                            :placeholder="
                                $t(
                                    'team.configure.shift_roles.fields.name_placeholder',
                                )
                            "
                            autocomplete="off"
                        />
                    </template>
                </FormField>
            </div>
        </Popup>

        <Popup
            v-model:open="templateModalOpen"
            :title="
                editingTemplate
                    ? $t('team.configure.templates.edit')
                    : $t('team.configure.templates.new')
            "
            :accept-label="
                editingTemplate
                    ? $t('team.configure.templates.actions.save')
                    : $t('team.configure.templates.actions.create')
            "
            :cancel-label="$t('ui.dialog.cancel')"
            :busy="templateForm.processing"
            class="max-w-xl"
            @accept="submitTemplate"
        >
            <div class="mt-5 space-y-4">
                <FormField
                    :label="$t('team.configure.templates.fields.name')"
                    :error="templateForm.errors.name"
                    required
                >
                    <template #default="{ id, invalid }">
                        <Input
                            :id="id"
                            v-model="templateForm.name"
                            :invalid="invalid"
                            :placeholder="
                                $t(
                                    'team.configure.templates.fields.name_placeholder',
                                )
                            "
                            autocomplete="off"
                        />
                    </template>
                </FormField>

                <FormField
                    :label="$t('team.configure.templates.fields.location')"
                    :error="templateForm.errors.location_id"
                    required
                >
                    <template #default="{ id, invalid }">
                        <Select
                            :id="id"
                            v-model="templateForm.location_id"
                            :invalid="invalid"
                        >
                            <option
                                v-for="location in locations"
                                :key="location.id"
                                :value="location.id"
                            >
                                {{ location.name }}
                            </option>
                        </Select>
                    </template>
                </FormField>

                <div>
                    <div class="mb-2 flex items-center justify-between gap-2">
                        <label class="text-sm font-bold">{{
                            $t('team.configure.templates.fields.roles')
                        }}</label>
                        <span class="text-xs font-bold text-muted">{{
                            $t(
                                'team.configure.templates.fields.needs_preview',
                                {
                                    count: templateNeedsPreview,
                                },
                            )
                        }}</span>
                    </div>
                    <p
                        v-if="templateForm.errors.roles"
                        class="mb-2 text-sm text-danger"
                    >
                        {{ templateForm.errors.roles }}
                    </p>
                    <div class="space-y-3">
                        <div
                            v-for="(line, index) in templateForm.roles"
                            :key="index"
                            class="grid grid-cols-[1fr_100px_auto] items-start gap-2"
                        >
                            <FormField
                                :label="
                                    $t('team.configure.templates.fields.role')
                                "
                                :error="
                                    templateForm.errors[
                                        `roles.${index}.shift_role_id`
                                    ]
                                "
                                required
                            >
                                <template #default="{ id, invalid }">
                                    <Select
                                        :id="id"
                                        v-model="line.shift_role_id"
                                        :invalid="invalid"
                                    >
                                        <option
                                            v-for="role in shiftRoles"
                                            :key="role.id"
                                            :value="role.id"
                                        >
                                            {{ role.name }}
                                        </option>
                                    </Select>
                                </template>
                            </FormField>
                            <FormField
                                :label="
                                    $t(
                                        'team.configure.templates.fields.headcount',
                                    )
                                "
                                :error="
                                    templateForm.errors[
                                        `roles.${index}.headcount`
                                    ]
                                "
                                required
                            >
                                <template #default="{ id, invalid }">
                                    <Input
                                        :id="id"
                                        v-model="line.headcount"
                                        :invalid="invalid"
                                        type="number"
                                        min="1"
                                        step="1"
                                    />
                                </template>
                            </FormField>
                            <IconButton
                                class="mt-7"
                                :icon="['fas', 'trash']"
                                :label="
                                    $t(
                                        'team.configure.templates.fields.remove_role',
                                    )
                                "
                                tone="delete"
                                :disabled="templateForm.roles.length <= 1"
                                @click="removeRoleLine(index)"
                            />
                        </div>
                    </div>
                    <Button
                        type="button"
                        variant="outline"
                        class="mt-3 min-h-9"
                        @click="addRoleLine"
                    >
                        <Icon
                            :name="['fas', 'plus']"
                            size="sm"
                        />
                        {{ $t('team.configure.templates.fields.add_role') }}
                    </Button>
                </div>
            </div>
        </Popup>
    </AppLayout>
</template>
