<script setup>
import HiddenPersonalInfo from '../../components/people/HiddenPersonalInfo.vue';
import AppLayout from '../../layouts/AppLayout.vue';
import TeamEngagementNoteLog from '../../components/notes/TeamEngagementNoteLog.vue';
import TeamPassAssignmentsPanel from '../../components/team/TeamPassAssignmentsPanel.vue';
import TeamMemberFields from '../../components/team/TeamMemberFields.vue';
import TeamMemberShiftsCard from '../../components/team/TeamMemberShiftsCard.vue';
import TeamMemberMeals from '../../components/team/TeamMemberMeals.vue';
import { Avatar } from '../../components/ui/avatar';
import { Button } from '../../components/ui/button';
import { Card, CardTitle } from '../../components/ui/card';
import { CustomDropdown } from '../../components/ui/custom-dropdown';
import { FormField } from '../../components/ui/form-field';
import { Icon } from '../../components/ui/icon';
import { useFlashToast } from '../../composables/useFlashToast';
import { toastFormErrors } from '../../lib/fieldError';
import { Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import { trans, transChoice } from 'laravel-vue-i18n';

const props = defineProps({
    engagement: { type: Object, required: true },
    notes: { type: Array, default: () => [] },
    event: { type: Object, required: true },
    groups: { type: Array, required: true },
    statuses: { type: Array, required: true },
    employmentTypes: { type: Array, required: true },
    roles: { type: Array, required: true },
    passes: { type: Array, default: () => [] },
    meals: { type: Object, default: null },
    canWrite: { type: Boolean, required: true },
    canAddNotes: { type: Boolean, required: true },
    canChangeRole: { type: Boolean, required: true },
    canReadNotes: { type: Boolean, required: true },
    canViewShifts: { type: Boolean, required: true },
    canReadMeals: { type: Boolean, required: true },
});

const form = useForm({
    name: props.engagement.name,
    email: props.engagement.email ?? '',
    phone: props.engagement.phone ?? '',
    status: props.engagement.status,
    employment_type: props.engagement.employment_type,
    hourly_pay: props.engagement.hourly_pay ?? '',
    group_id: props.engagement.group_id ?? '',
    role_id: props.engagement.role_id ?? '',
    pass_assignments: props.engagement.pass_assignments.map((assignment) => ({
        id: assignment.id,
        pass_type_id: assignment.pass_type_id,
        issue_state: assignment.issue_state,
        can_remove: assignment.can_remove,
    })),
    notes: [],
    note_edits: [],
});
const { showError, showFormError } = useFlashToast();
const readOnly = computed(() => !props.canWrite);
const hired = computed(() => form.status === 'hired');
const persistedHired = computed(() => props.engagement.status === 'hired');
const subtitle = computed(() =>
    trans(`team.advancement.status.${form.status}`),
);
const breadcrumbs = computed(() => [
    { label: trans('app.name'), href: '/dashboard' },
    { label: trans('team.title'), href: '/team/advancement' },
    { label: trans('nav.team.advancement'), href: '/team/advancement' },
    { label: props.engagement.name },
]);
const sections = computed(() => [
    {
        key: 'contracts',
        title: trans('team.member.sections.contracts.title'),
        description: trans('team.member.sections.contracts.description'),
        available: true,
    },
]);
const roleItems = computed(() => {
    const items = [
        { value: '', title: trans('team.member.role.no_role') },
        ...props.roles.map((role) => ({ value: role.id, title: role.name })),
    ];

    if (
        props.engagement.role &&
        !items.some((item) => item.value === props.engagement.role.id)
    ) {
        items.push({
            value: props.engagement.role.id,
            title: props.engagement.role.active
                ? props.engagement.role.name
                : `${props.engagement.role.name} ${trans('settings.team.role_off_suffix')}`,
            disabled: true,
        });
    }

    return items;
});
const unsavedSummary = computed(() => {
    const newCount = form.notes.length;
    const editedCount = form.note_edits.length;

    if (newCount && editedCount) {
        return [
            transChoice('team.member.unsaved.new_notes', newCount, {
                count: newCount,
            }),
            transChoice('team.member.unsaved.edited_notes_short', editedCount, {
                count: editedCount,
            }),
        ].join(' · ');
    }
    if (newCount) {
        return transChoice('team.member.unsaved.new_notes', newCount, {
            count: newCount,
        });
    }
    if (editedCount) {
        return transChoice('team.member.unsaved.edited_notes', editedCount, {
            count: editedCount,
        });
    }

    return form.isDirty ? trans('team.member.unsaved.changes') : '';
});
const submit = () => {
    if (!props.canWrite && !props.canAddNotes && !props.canChangeRole) return;
    form.transform((data) => ({
        ...(props.canWrite
            ? Object.fromEntries(
                  Object.entries(data).filter(
                      ([key]) =>
                          !['notes', 'note_edits', 'role_id'].includes(key),
                  ),
              )
            : {}),
        ...(props.canChangeRole ? { role_id: data.role_id } : {}),
        ...(props.canAddNotes
            ? { notes: data.notes, note_edits: data.note_edits }
            : {}),
    }));
    form.put(`/team/members/${props.engagement.id}`, {
        onSuccess: () => {
            form.notes = [];
            form.note_edits = [];
            form.defaults();
        },
        onError: (errors) => {
            if (
                Object.keys(errors).some((key) =>
                    key.startsWith('pass_assignments'),
                )
            ) {
                showFormError(errors);
                return;
            }

            toastFormErrors(form, errors, { showError, showFormError });
        },
    });
};
</script>

<template>
    <AppLayout
        :title="engagement.name"
        :breadcrumbs="breadcrumbs"
        compact-mobile-header
    >
        <div class="container mx-auto pb-24">
            <header class="mb-5 flex items-center gap-3.5">
                <Avatar
                    :name="engagement.name"
                    size="lg"
                />
                <div>
                    <h1 class="m-0 text-[26px] font-bold tracking-tight">
                        {{ engagement.name }}
                    </h1>
                    <p class="mt-0.5 mb-0 text-sm text-muted">
                        {{ subtitle }}
                    </p>
                </div>
            </header>

            <p class="mt-0 mb-5 text-sm text-muted">
                {{ $t('team.member.unlock_summary') }}
            </p>
            <p
                v-if="event.locked"
                class="mb-4 flex items-center gap-2 rounded-lg border border-warning/20 bg-warning/10 p-3 text-sm text-charcoal"
                role="status"
            >
                <Icon :name="['fas', 'lock']" />
                {{ $t('team.member.locked') }}
            </p>

            <form
                id="team-member-details"
                @submit.prevent="submit"
            >
                <Card>
                    <CardTitle>
                        {{ $t('team.member.details') }}
                    </CardTitle>
                    <p class="mt-1 mb-4 text-xs text-muted">
                        {{ $t('team.member.details_hint') }}
                    </p>
                    <dl
                        v-if="readOnly"
                        class="mt-4 grid gap-4 sm:grid-cols-2"
                    >
                        <div
                            v-for="field in [
                                'name',
                                'status',
                                'employment_type',
                                'phone',
                                'email',
                                'group',
                            ]"
                            :key="field"
                        >
                            <dt class="text-sm text-muted">
                                {{ $t(`team.member.fields.${field}`) }}
                            </dt>
                            <dd class="mt-1">
                                <HiddenPersonalInfo
                                    v-if="
                                        engagement.personal_info_hidden &&
                                        ['email', 'phone'].includes(field)
                                    "
                                /><span v-else>{{
                                    field === 'group'
                                        ? engagement.group?.name
                                        : [
                                                'status',
                                                'employment_type',
                                            ].includes(field)
                                          ? $t(
                                                `team.advancement.${field}.${engagement[field]}`,
                                            )
                                          : engagement[field]
                                }}</span>
                            </dd>
                        </div>
                    </dl>
                    <TeamMemberFields
                        v-else
                        :form="form"
                        :groups="groups"
                        :statuses="statuses"
                        :employment-types="employmentTypes"
                        :disabled="readOnly || form.processing"
                        @update="(field, value) => (form[field] = value)"
                    />
                </Card>

                <Card class="mt-4">
                    <CardTitle>
                        {{ $t('team.member.role.title') }}
                    </CardTitle>
                    <div class="mt-4 max-w-md">
                        <FormField
                            v-slot="{ id, invalid }"
                            :label="$t('team.member.role.field')"
                            :error="form.errors.role_id"
                        >
                            <p
                                v-if="!canChangeRole"
                                class="m-0"
                            >
                                {{
                                    engagement.role?.name ??
                                    $t('team.member.role.no_role')
                                }}
                            </p>
                            <CustomDropdown
                                v-else
                                :id="id"
                                v-model="form.role_id"
                                :items="roleItems"
                                :invalid="invalid"
                                :disabled="!canChangeRole || form.processing"
                            />
                        </FormField>
                    </div>
                    <p
                        v-if="engagement.role && !engagement.role.active"
                        class="mt-2 mb-0 flex items-start gap-1.5 text-xs text-muted"
                    >
                        <Icon
                            :name="['fas', 'power-off']"
                            size="sm"
                            class="mt-0.5"
                        />
                        {{ $t('team.member.role.off_hint') }}
                    </p>
                    <p class="mt-2 mb-0 text-xs text-muted">
                        {{
                            $t('team.member.role.hint', {
                                event: event.name,
                            })
                        }}
                        <Link
                            v-if="$page.props.auth.user.is_admin"
                            href="/settings/team"
                            class="font-semibold text-secondary no-underline hover:underline"
                        >
                            {{ $t('settings.team.title') }} </Link
                        >.
                    </p>
                </Card>

                <TeamMemberShiftsCard
                    v-if="canViewShifts"
                    :key="engagement.id"
                    :member-id="engagement.id"
                />

                <Card
                    id="passes"
                    class="mt-4"
                >
                    <TeamPassAssignmentsPanel
                        v-model:assignments="form.pass_assignments"
                        :passes="passes"
                        :member-name="engagement.name"
                        :can-write="canWrite"
                        :hired="persistedHired"
                        :errors="form.errors"
                    />
                </Card>

                <Card
                    v-for="section in sections"
                    :id="section.key"
                    :key="section.key"
                    class="mt-4"
                >
                    <CardTitle>
                        {{ section.title }}
                    </CardTitle>
                    <p class="mt-1 mb-3 text-xs text-muted">
                        {{ section.description }}
                    </p>
                    <div
                        class="rounded-lg border border-dashed border-line bg-page p-5 text-center text-sm text-muted"
                    >
                        {{
                            $t(
                                section.available
                                    ? 'team.member.sections.future'
                                    : 'team.member.sections.hired_required',
                            )
                        }}
                    </div>
                </Card>

                <TeamMemberMeals
                    v-if="persistedHired && canReadMeals && meals"
                    :key="engagement.id"
                    :meals="meals"
                    class="mt-4"
                />

                <TeamEngagementNoteLog
                    v-if="canReadNotes"
                    v-model:new-notes="form.notes"
                    v-model:note-edits="form.note_edits"
                    class="mt-4"
                    :notes="notes"
                    :can-write="canAddNotes"
                    :timezone="event.timezone"
                    :errors="form.errors"
                />

                <p
                    class="mt-4 rounded-lg border border-dashed border-line bg-page p-3 text-sm text-muted"
                >
                    <strong class="text-charcoal">{{
                        $t('team.member.unlock_note_label')
                    }}</strong>
                    {{ $t('team.member.unlock_note') }}
                </p>
            </form>

            <div
                v-if="canWrite || canAddNotes || canChangeRole"
                class="fixed right-0 bottom-0 left-0 z-30 border-t border-line bg-ground/95 py-3 backdrop-blur lg:left-[var(--app-sidebar-width)]"
            >
                <div class="content-body container mx-auto">
                    <div class="mx-auto flex items-center justify-between">
                        <Button
                            href="/team/advancement"
                            variant="cancel"
                            :disabled="form.processing"
                        >
                            {{ $t('setup.actions.cancel') }}
                        </Button>
                        <div class="flex items-center gap-3">
                            <span
                                v-if="unsavedSummary"
                                class="text-xs font-semibold text-warning"
                            >
                                {{ unsavedSummary }}
                            </span>
                            <Button
                                form="team-member-details"
                                type="submit"
                                :loading="form.processing"
                            >
                                {{ $t('team.member.save') }}
                            </Button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
