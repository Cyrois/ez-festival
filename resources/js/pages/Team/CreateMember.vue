<script setup>
import AppLayout from '../../layouts/AppLayout.vue';
import TeamMemberFields from '../../components/team/TeamMemberFields.vue';
import { Button } from '../../components/ui/button';
import { Card } from '../../components/ui/card';
import { useFlashToast } from '../../composables/useFlashToast';
import { toastFormErrors } from '../../lib/fieldError';
import { useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import { trans } from 'laravel-vue-i18n';

const props = defineProps({
    event: { type: Object, required: true },
    groups: { type: Array, required: true },
    statuses: { type: Array, required: true },
    employmentTypes: { type: Array, required: true },
});

const form = useForm({
    name: '',
    email: '',
    phone: '',
    status: 'applied',
    employment_type: 'volunteer',
    role_title: '',
    hourly_pay: '',
    group_id: '',
});
const { showError, showFormError } = useFlashToast();
const breadcrumbs = computed(() => [
    { label: trans('app.name'), href: '/dashboard' },
    { label: trans('team.title'), href: '/team/advancement' },
    { label: trans('nav.team.advancement'), href: '/team/advancement' },
    { label: trans('team.member.add') },
]);
const submit = () => {
    form.post(`/team/events/${props.event.id}/members`, {
        onError: (errors) =>
            toastFormErrors(form, errors, { showError, showFormError }),
    });
};
</script>

<template>
    <AppLayout
        :title="$t('team.member.add')"
        :breadcrumbs="breadcrumbs"
        back-href="/team/advancement"
        :back-label="$t('team.member.back')"
    >
        <div class="container mx-auto max-w-3xl">
            <header class="mb-6">
                <h1 class="m-0 text-2xl font-bold tracking-tight">
                    {{ $t('team.member.add') }}
                </h1>
                <p class="mt-1 mb-0 text-sm text-muted">
                    {{ $t('team.member.create_lead', { event: event.name }) }}
                </p>
            </header>
            <Card>
                <form
                    class="space-y-5"
                    @submit.prevent="submit"
                >
                    <TeamMemberFields
                        :form="form"
                        :groups="groups"
                        :statuses="statuses"
                        :employment-types="employmentTypes"
                        :disabled="form.processing"
                        @update="(field, value) => (form[field] = value)"
                    />
                    <div
                        class="flex justify-end gap-2 border-t border-line pt-5"
                    >
                        <Button
                            href="/team/advancement"
                            variant="ghost"
                            :disabled="form.processing"
                        >
                            {{ $t('setup.actions.cancel') }}
                        </Button>
                        <Button
                            type="submit"
                            :loading="form.processing"
                        >
                            {{ $t('team.member.save') }}
                        </Button>
                    </div>
                </form>
            </Card>
        </div>
    </AppLayout>
</template>
