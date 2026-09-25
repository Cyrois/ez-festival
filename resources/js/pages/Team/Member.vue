<script setup>
import AppLayout from '../../layouts/AppLayout.vue';
import TeamMemberFields from '../../components/team/TeamMemberFields.vue';
import { Avatar } from '../../components/ui/avatar';
import { Button } from '../../components/ui/button';
import { Card } from '../../components/ui/card';
import { Icon } from '../../components/ui/icon';
import { useFlashToast } from '../../composables/useFlashToast';
import { toastFormErrors } from '../../lib/fieldError';
import { useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import { trans } from 'laravel-vue-i18n';

const props = defineProps({
    engagement: { type: Object, required: true },
    event: { type: Object, required: true },
    groups: { type: Array, required: true },
    statuses: { type: Array, required: true },
    employmentTypes: { type: Array, required: true },
    canWrite: { type: Boolean, required: true },
});

const form = useForm({
    name: props.engagement.name,
    email: props.engagement.email ?? '',
    phone: props.engagement.phone ?? '',
    status: props.engagement.status,
    employment_type: props.engagement.employment_type,
    role_title: props.engagement.role_title ?? '',
    hourly_pay: props.engagement.hourly_pay ?? '',
    group_id: props.engagement.group_id ?? '',
});
const { showError, showFormError } = useFlashToast();
const readOnly = computed(() => !props.canWrite);
const hired = computed(() => form.status === 'hired');
const subtitle = computed(() =>
    [trans(`team.advancement.status.${form.status}`), form.role_title]
        .filter(Boolean)
        .join(' · '),
);
const breadcrumbs = computed(() => [
    { label: trans('app.name'), href: '/dashboard' },
    { label: trans('team.title'), href: '/team/advancement' },
    { label: trans('nav.team.advancement'), href: '/team/advancement' },
    { label: props.engagement.name },
]);
const sections = computed(() => [
    {
        key: 'shifts',
        title: trans('team.member.sections.shifts.title'),
        description: trans('team.member.sections.shifts.description'),
        available: hired.value,
    },
    {
        key: 'passes',
        title: trans('team.member.sections.passes.title'),
        description: trans('team.member.sections.passes.description'),
        available: hired.value,
    },
    {
        key: 'contracts',
        title: trans('team.member.sections.contracts.title'),
        description: trans('team.member.sections.contracts.description'),
        available: true,
    },
    {
        key: 'meals',
        title: trans('team.member.sections.meals.title'),
        description: trans('team.member.sections.meals.description'),
        available: hired.value,
    },
]);
const submit = () => {
    if (readOnly.value) return;
    form.put(`/team/members/${props.engagement.id}`, {
        onError: (errors) =>
            toastFormErrors(form, errors, { showError, showFormError }),
    });
};
</script>

<template>
    <AppLayout
        :title="engagement.name"
        :breadcrumbs="breadcrumbs"
        back-href="/team/advancement"
        :back-label="$t('team.member.back')"
    >
        <div class="container mx-auto max-w-6xl pb-24">
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
                v-if="readOnly"
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
                    <h2 class="m-0 text-xl font-bold text-muted">
                        {{ $t('team.member.details') }}
                    </h2>
                    <p class="mt-1 mb-4 text-xs text-muted">
                        {{ $t('team.member.details_hint') }}
                    </p>
                    <TeamMemberFields
                        :form="form"
                        :groups="groups"
                        :statuses="statuses"
                        :employment-types="employmentTypes"
                        :disabled="readOnly || form.processing"
                        @update="(field, value) => (form[field] = value)"
                    />
                </Card>

                <Card
                    v-for="section in sections"
                    :id="section.key"
                    :key="section.key"
                    class="mt-4"
                >
                    <h2 class="m-0 text-xl font-bold text-muted">
                        {{ section.title }}
                    </h2>
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
                v-if="!readOnly"
                class="fixed right-0 bottom-0 left-0 z-30 border-t border-line bg-ground/95 py-3 backdrop-blur lg:left-56"
            >
                <div class="container mx-auto px-4 md:px-6">
                    <div
                        class="mx-auto flex max-w-6xl items-center justify-between"
                    >
                        <Button
                            href="/team/advancement"
                            variant="cancel"
                            :disabled="form.processing"
                        >
                            {{ $t('setup.actions.cancel') }}
                        </Button>
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
    </AppLayout>
</template>
