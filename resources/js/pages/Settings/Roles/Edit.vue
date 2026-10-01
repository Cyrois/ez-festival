<script setup>
import SettingsLayout from '../../../layouts/SettingsLayout.vue';
import { Badge } from '../../../components/ui/badge';
import { Button } from '../../../components/ui/button';
import { Card } from '../../../components/ui/card';
import { Checkbox } from '../../../components/ui/checkbox';
import { Dialog } from '../../../components/ui/dialog';
import { FormField } from '../../../components/ui/form-field';
import { Icon } from '../../../components/ui/icon';
import { Input } from '../../../components/ui/input';
import { useFlashToast } from '../../../composables/useFlashToast';
import { emphasisParts } from '../../../lib/emphasisParts';
import { fieldError, toastFormErrors } from '../../../lib/fieldError';
import { roleMatchHint } from '../roleMatchHint';
import { router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { trans, transChoice } from 'laravel-vue-i18n';

const props = defineProps({
    role: { type: Object, required: true },
});

const breadcrumbs = computed(() => [
    { label: trans('app.name'), href: '/dashboard' },
    { label: trans('nav.settings'), href: '/settings/events' },
    { label: trans('settings.roles.title'), href: '/settings/roles' },
    { label: props.role.name },
]);

const { showError, showSuccess, showFormError } = useFlashToast();
const submittedName = ref(null);
const turnOffOpen = ref(false);
const statusBusy = ref(false);
const form = useForm({
    name: props.role.name,
    can_read_team_notes: props.role.can_read_team_notes,
});

const peopleLabel = computed(() =>
    transChoice('settings.roles.people_count', props.role.people_count, {
        count: props.role.people_count,
    }),
);

const matchParts = computed(() => {
    const hint = roleMatchHint({
        match: form.errors.name_match,
        submitted: submittedName.value,
        current: form.name,
    });

    return hint
        ? emphasisParts(trans, 'settings.roles.form.match_hint', hint)
        : [];
});

const submit = () => {
    form.clearErrors();
    submittedName.value = form.name;
    form.put(`/settings/roles/${props.role.id}`, {
        preserveScroll: true,
        onSuccess: () => showSuccess(trans('settings.roles.toast.updated')),
        onError: (errors) =>
            toastFormErrors(form, errors, { showError, showFormError }),
    });
};

const setActive = (active) => {
    if (statusBusy.value) {
        return;
    }

    statusBusy.value = true;
    router.put(
        `/settings/roles/${props.role.id}/status`,
        { active },
        {
            preserveScroll: true,
            onSuccess: () => {
                turnOffOpen.value = false;
            },
            onFinish: () => {
                statusBusy.value = false;
            },
        },
    );
};
</script>

<template>
    <SettingsLayout
        :title="$t('settings.roles.edit.title')"
        :breadcrumbs="breadcrumbs"
    >
        <div class="container mx-auto pb-24">
            <header
                class="mb-5 flex flex-wrap items-start justify-between gap-4"
            >
                <div>
                    <div class="flex items-center gap-2">
                        <h1 class="m-0 text-2xl font-bold tracking-tight">
                            {{ $t('settings.roles.edit.title') }}
                        </h1>
                        <Badge
                            v-if="!role.active"
                            pill
                        >
                            {{ $t('settings.roles.status.off') }}
                        </Badge>
                    </div>
                    <p class="mt-1 mb-0 text-sm text-muted">
                        {{
                            $t('settings.roles.edit.lead', { name: role.name })
                        }}
                    </p>
                </div>
            </header>

            <div class="mx-auto flex max-w-4xl flex-col gap-4">
                <Card class="p-6">
                    <form @submit.prevent="submit">
                        <FormField
                            :label="$t('settings.roles.form.name')"
                            :error="fieldError(form, 'name')"
                            required
                        >
                            <template #default="{ id, invalid }">
                                <Input
                                    :id="id"
                                    v-model="form.name"
                                    type="text"
                                    :invalid="invalid"
                                    maxlength="255"
                                    autocomplete="off"
                                />
                            </template>
                        </FormField>
                        <p class="mt-1 mb-0 text-xs leading-snug text-muted">
                            {{ $t('settings.roles.form.unique_hint') }}
                            <template v-if="matchParts.length">
                                <template
                                    v-for="(part, index) in matchParts"
                                    :key="index"
                                >
                                    <code
                                        v-if="part.emphasis"
                                        class="rounded border border-line bg-page px-1 font-mono text-[11px] whitespace-pre text-charcoal"
                                        >{{ part.text }}</code
                                    >
                                    <template v-else>{{ part.text }}</template>
                                </template>
                            </template>
                        </p>
                        <p class="mt-2 mb-0 text-xs leading-snug text-muted">
                            {{
                                $t('settings.roles.form.rename_keeps', {
                                    people: peopleLabel,
                                })
                            }}
                        </p>

                        <div class="mt-6 border-t border-line pt-5">
                            <h2
                                class="m-0 text-base font-semibold text-charcoal"
                            >
                                {{ $t('settings.roles.permissions.title') }}
                            </h2>
                            <Checkbox
                                v-model="form.can_read_team_notes"
                                class="mt-3"
                            >
                                <span class="font-semibold">
                                    {{
                                        $t(
                                            'settings.roles.permissions.can_read_team_notes',
                                        )
                                    }}
                                </span>
                            </Checkbox>
                            <p
                                class="mt-1 mb-0 pl-6 text-xs leading-snug text-muted"
                            >
                                {{
                                    $t(
                                        'settings.roles.permissions.can_read_team_notes_help',
                                    )
                                }}
                            </p>
                        </div>
                    </form>
                </Card>

                <Card
                    class="flex flex-wrap items-center justify-between gap-4 p-6"
                >
                    <div>
                        <h2 class="m-0 text-base font-semibold text-charcoal">
                            {{ $t('settings.roles.edit.status_title') }}
                        </h2>
                        <p class="mt-1 mb-0 text-sm text-muted">
                            {{ $t('settings.roles.off_note') }}
                        </p>
                    </div>
                    <Button
                        type="button"
                        :variant="
                            role.active ? 'outline-danger' : 'outline-primary'
                        "
                        :loading="statusBusy"
                        :disabled="statusBusy"
                        @click="
                            role.active ? (turnOffOpen = true) : setActive(true)
                        "
                    >
                        <Icon
                            :name="['fas', 'power-off']"
                            size="sm"
                        />
                        {{
                            role.active
                                ? $t('settings.roles.actions.turn_off')
                                : $t('settings.roles.actions.turn_on')
                        }}
                    </Button>
                </Card>
            </div>
        </div>

        <div
            class="fixed right-0 bottom-0 left-0 z-30 border-t border-line bg-ground/95 py-3 backdrop-blur lg:left-[var(--app-sidebar-width)]"
        >
            <div class="container mx-auto px-4 md:px-6">
                <div
                    class="mx-auto flex max-w-4xl items-center justify-between"
                >
                    <Button
                        href="/settings/roles"
                        variant="outline"
                    >
                        {{ $t('settings.roles.edit.back') }}
                    </Button>
                    <Button
                        type="button"
                        :loading="form.processing"
                        :disabled="form.processing"
                        @click="submit"
                    >
                        {{ $t('settings.roles.form.save') }}
                    </Button>
                </div>
            </div>
        </div>

        <Dialog
            v-model:open="turnOffOpen"
            :title="$t('settings.roles.turn_off.title')"
            :description="
                $t('settings.roles.turn_off.body', {
                    name: role.name,
                    people: peopleLabel,
                })
            "
            :confirm-label="$t('settings.roles.turn_off.confirm')"
            :cancel-label="$t('settings.roles.form.cancel')"
            confirm-variant="danger"
            :busy="statusBusy"
            @confirm="setActive(false)"
        />
    </SettingsLayout>
</template>
