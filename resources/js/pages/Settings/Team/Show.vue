<script setup>
import SettingsLayout from '../../../layouts/SettingsLayout.vue';
import { Avatar } from '../../../components/ui/avatar';
import { Button } from '../../../components/ui/button';
import { Card } from '../../../components/ui/card';
import { CustomDropdown } from '../../../components/ui/custom-dropdown';
import { FormField } from '../../../components/ui/form-field';
import { Icon } from '../../../components/ui/icon';
import { Input } from '../../../components/ui/input';
import { Switch } from '../../../components/ui/switch';
import { useFlashToast } from '../../../composables/useFlashToast';
import { toastFormErrors } from '../../../lib/fieldError';
import { router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { trans } from 'laravel-vue-i18n';

const props = defineProps({
    person: { type: Object, required: true },
    events: { type: Array, required: true },
    roles: { type: Array, required: true },
    statuses: { type: Array, required: true },
});

const originalRoles = Object.fromEntries(
    props.events.map((event) => [event.id, event.role_id ?? '']),
);
const form = useForm({
    name: props.person.name,
    phone: props.person.phone ?? '',
    can_log_in: props.person.can_log_in,
    event_access: props.events.map((event) => ({
        event_id: event.id,
        role_id: event.role_id ?? '',
        status: null,
    })),
});
const { showError, showFormError } = useFlashToast();
const temporaryPassword = ref('');
const generatingPassword = ref(false);
const copied = ref(false);
const inviteCancelledLocally = ref(false);

const inviteWillBeSent = computed(
    () =>
        !props.person.can_log_in &&
        form.can_log_in &&
        !props.person.has_set_password,
);
const canGeneratePassword = computed(
    () => props.person.can_log_in && form.can_log_in,
);
const canResendInvite = computed(
    () => props.person.can_resend_invite && !inviteCancelledLocally.value,
);

const breadcrumbs = computed(() => [
    { label: trans('app.name'), href: '/dashboard' },
    { label: trans('nav.settings'), href: '/settings/events' },
    { label: trans('settings.team.title'), href: '/settings/team' },
    { label: props.person.name },
]);
const statusItems = computed(() =>
    props.statuses.map((status) => ({
        value: status,
        title: trans(`team.advancement.status.${status}`),
    })),
);
const roleItemsFor = (event) => {
    const items = [
        { value: '', title: trans('settings.team.no_access') },
        ...props.roles.map((role) => ({ value: role.id, title: role.name })),
    ];

    if (event.role && !event.role.active) {
        items.push({
            value: event.role.id,
            title: `${event.role.name} ${trans('settings.team.role_off_suffix')}`,
            disabled: true,
        });
    }

    return items;
};
const newlyGranted = (event, access) =>
    originalRoles[event.id] === '' && access.role_id !== '';
const updateRole = (event, access, value) => {
    const wasNoAccess = access.role_id === '';
    access.role_id = value;

    if (value === '') {
        access.status = null;
    } else if (originalRoles[event.id] === '' && wasNoAccess) {
        access.status = 'hired';
    }
};
const submit = () => {
    form.transform((data) => ({
        ...data,
        event_access: data.event_access.map((access) => ({
            ...access,
            role_id: access.role_id === '' ? null : access.role_id,
        })),
    })).put(`/settings/team/${props.person.id}`, {
        onError: (errors) =>
            toastFormErrors(form, errors, { showError, showFormError }),
    });
};
const resendInvite = () => {
    router.post(
        `/settings/team/${props.person.id}/invite`,
        {},
        {
            preserveScroll: true,
            onError: () =>
                showError(trans('settings.team.login.action_failed')),
        },
    );
};
const generatePassword = async () => {
    generatingPassword.value = true;
    copied.value = false;

    try {
        const response = await fetch(
            `/settings/team/${props.person.id}/temporary-password`,
            {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': document
                        .querySelector('meta[name="csrf-token"]')
                        .getAttribute('content'),
                },
            },
        );

        if (!response.ok) throw new Error('Password generation failed');

        temporaryPassword.value = (
            await response.json()
        ).data.temporary_password;
        inviteCancelledLocally.value = true;
    } catch {
        showError(trans('settings.team.login.action_failed'));
    } finally {
        generatingPassword.value = false;
    }
};
const copyPassword = async () => {
    await navigator.clipboard.writeText(temporaryPassword.value);
    copied.value = true;
};
</script>

<template>
    <SettingsLayout
        :title="person.name"
        :breadcrumbs="breadcrumbs"
    >
        <div class="container mx-auto max-w-5xl pb-24">
            <Button
                href="/settings/team"
                variant="ghost"
                size="sm"
                class="mb-3 px-2.5"
            >
                <Icon
                    :name="['fas', 'arrow-left']"
                    size="sm"
                />
                {{ $t('settings.team.back') }}
            </Button>

            <header class="mb-5 flex items-center gap-3.5">
                <Avatar
                    :name="person.name"
                    size="lg"
                />
                <div>
                    <h1 class="m-0 text-[26px] font-bold tracking-tight">
                        {{ person.name }}
                    </h1>
                    <p class="mt-0.5 mb-0 text-sm text-muted">
                        {{ person.email }}
                    </p>
                </div>
            </header>

            <form
                id="global-team-person"
                class="space-y-4"
                @submit.prevent="submit"
            >
                <Card>
                    <h2 class="m-0 text-lg font-bold text-muted">
                        {{ $t('settings.team.details') }}
                    </h2>
                    <p class="mt-1 mb-4 text-xs text-muted">
                        {{ $t('settings.team.details_hint') }}
                    </p>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <FormField
                            v-slot="{ id, invalid }"
                            :label="$t('settings.team.fields.name')"
                            :error="form.errors.name"
                            required
                        >
                            <Input
                                :id="id"
                                v-model="form.name"
                                :invalid="invalid"
                                maxlength="255"
                                required
                            />
                        </FormField>
                        <FormField :label="$t('settings.team.fields.email')">
                            <Input
                                :model-value="person.email"
                                type="email"
                                disabled
                            />
                        </FormField>
                        <FormField
                            v-slot="{ id, invalid }"
                            :label="$t('settings.team.fields.phone')"
                            :error="form.errors.phone"
                        >
                            <Input
                                :id="id"
                                v-model="form.phone"
                                type="tel"
                                :invalid="invalid"
                                maxlength="50"
                            />
                        </FormField>
                    </div>
                    <div class="mt-5 border-t border-line pt-5">
                        <Switch
                            v-model="form.can_log_in"
                            :disabled="
                                form.processing ||
                                Boolean(person.login_disable_reason)
                            "
                        >
                            {{ $t('settings.team.login.label') }}
                        </Switch>
                        <p
                            class="mt-1.5 mb-0 text-xs"
                            :class="
                                person.login_disable_reason
                                    ? 'text-warning'
                                    : 'text-muted'
                            "
                        >
                            {{
                                person.login_disable_reason ||
                                $t('settings.team.login.edit_hint')
                            }}
                        </p>
                        <p
                            v-if="form.errors.can_log_in"
                            class="mt-1.5 mb-0 text-sm text-danger"
                            role="alert"
                        >
                            {{ form.errors.can_log_in }}
                        </p>
                        <div
                            v-if="inviteWillBeSent"
                            class="mt-4 flex items-start gap-2 rounded-lg border border-secondary/30 bg-secondary/10 p-3 text-sm text-secondary"
                        >
                            <Icon
                                :name="['fas', 'envelope']"
                                size="sm"
                                class="mt-0.5"
                            />
                            <p class="m-0">
                                {{
                                    $t('settings.team.login.invite_note', {
                                        email: person.email,
                                    })
                                }}
                            </p>
                        </div>

                        <div
                            v-if="canGeneratePassword"
                            class="mt-5 space-y-3"
                        >
                            <div
                                v-if="temporaryPassword"
                                class="rounded-lg border border-line bg-page p-4"
                            >
                                <p class="m-0 text-xs font-bold text-muted">
                                    {{
                                        $t(
                                            'settings.team.login.temporary_label',
                                        )
                                    }}
                                </p>
                                <div
                                    class="mt-2 flex flex-wrap items-center gap-2"
                                >
                                    <code
                                        class="rounded-lg bg-white px-3 py-2 font-mono text-sm font-bold"
                                        >{{ temporaryPassword }}</code
                                    >
                                    <Button
                                        variant="secondary"
                                        size="sm"
                                        @click="copyPassword"
                                    >
                                        <Icon
                                            :name="['fas', 'copy']"
                                            size="sm"
                                        />
                                        {{
                                            copied
                                                ? $t(
                                                      'settings.team.login.copied',
                                                  )
                                                : $t('settings.team.login.copy')
                                        }}
                                    </Button>
                                </div>
                                <div class="mt-3 space-y-1 text-xs text-muted">
                                    <p class="m-0">
                                        {{
                                            $t(
                                                'settings.team.login.temporary_change',
                                            )
                                        }}
                                    </p>
                                    <p class="m-0">
                                        {{
                                            $t(
                                                'settings.team.login.temporary_no_email',
                                            )
                                        }}
                                    </p>
                                    <p class="m-0">
                                        {{
                                            $t(
                                                'settings.team.login.temporary_once',
                                            )
                                        }}
                                    </p>
                                </div>
                            </div>

                            <div>
                                <Button
                                    variant="secondary"
                                    :loading="generatingPassword"
                                    @click="generatePassword"
                                >
                                    <Icon
                                        :name="['fas', 'key']"
                                        size="sm"
                                    />
                                    {{
                                        $t(
                                            'settings.team.login.generate_password',
                                        )
                                    }}
                                </Button>
                                <p class="mt-1.5 mb-0 text-xs text-muted">
                                    {{
                                        $t(
                                            temporaryPassword
                                                ? 'settings.team.login.generate_again_hint'
                                                : 'settings.team.login.generate_hint',
                                        )
                                    }}
                                </p>
                            </div>
                        </div>

                        <button
                            v-if="
                                canResendInvite &&
                                person.can_log_in &&
                                form.can_log_in
                            "
                            type="button"
                            class="mt-4 text-sm font-bold text-secondary hover:underline"
                            @click="resendInvite"
                        >
                            {{ $t('settings.team.login.resend') }}
                        </button>
                    </div>
                </Card>

                <Card>
                    <h2 class="m-0 text-lg font-bold text-muted">
                        {{ $t('settings.team.access.title') }}
                    </h2>
                    <p class="mt-1 mb-4 text-xs text-muted">
                        {{ $t('settings.team.access.lead') }}
                    </p>

                    <div class="overflow-visible rounded-lg border border-line">
                        <div
                            v-for="(event, index) in events"
                            :key="event.id"
                            class="grid gap-3 border-b border-line p-4 last:border-b-0 md:grid-cols-[1fr_20rem] md:items-center"
                        >
                            <div>
                                <p class="m-0 font-semibold">
                                    {{ event.name }}
                                </p>
                                <p class="mt-0.5 mb-0 text-xs text-muted">
                                    {{ event.starts_on }} – {{ event.ends_on }}
                                </p>
                            </div>
                            <div>
                                <CustomDropdown
                                    :model-value="
                                        form.event_access[index].role_id
                                    "
                                    :items="roleItemsFor(event)"
                                    :disabled="event.locked || form.processing"
                                    :invalid="
                                        Boolean(
                                            form.errors[
                                                `event_access.${index}.role_id`
                                            ],
                                        )
                                    "
                                    @update:model-value="
                                        updateRole(
                                            event,
                                            form.event_access[index],
                                            $event,
                                        )
                                    "
                                />

                                <div
                                    v-if="
                                        newlyGranted(
                                            event,
                                            form.event_access[index],
                                        )
                                    "
                                    class="mt-2 rounded-lg border border-line bg-page p-3"
                                >
                                    <FormField
                                        v-slot="{ id, invalid }"
                                        :label="
                                            $t('settings.team.fields.status')
                                        "
                                        :error="
                                            form.errors[
                                                `event_access.${index}.status`
                                            ]
                                        "
                                        :hint="
                                            $t(
                                                'settings.team.access.new_status_hint',
                                            )
                                        "
                                    >
                                        <CustomDropdown
                                            :id="id"
                                            v-model="
                                                form.event_access[index].status
                                            "
                                            :items="statusItems"
                                            :invalid="invalid"
                                        />
                                    </FormField>
                                </div>

                                <p
                                    v-if="event.role && !event.role.active"
                                    class="mt-1.5 mb-0 flex items-start gap-1.5 text-xs text-muted"
                                >
                                    <Icon
                                        :name="['fas', 'power-off']"
                                        size="sm"
                                        class="mt-0.5"
                                    />
                                    {{
                                        $t('settings.team.access.off_role_hint')
                                    }}
                                </p>
                                <p
                                    v-if="event.locked"
                                    class="mt-1.5 mb-0 flex items-center gap-1.5 text-xs text-muted"
                                >
                                    <Icon
                                        :name="['fas', 'lock']"
                                        size="sm"
                                    />
                                    {{ $t('settings.team.access.locked') }}
                                </p>
                            </div>
                        </div>
                    </div>
                    <p class="mt-3 mb-0 text-xs text-muted">
                        {{ $t('settings.team.access.remove_note') }}
                    </p>
                </Card>
            </form>

            <div
                class="fixed right-0 bottom-0 left-0 z-30 border-t border-line bg-ground/95 py-3 backdrop-blur lg:left-[var(--app-sidebar-width)]"
            >
                <div class="container mx-auto px-4 md:px-6">
                    <div
                        class="mx-auto flex max-w-5xl items-center justify-between"
                    >
                        <Button
                            href="/settings/team"
                            variant="cancel"
                            :disabled="form.processing"
                        >
                            {{ $t('setup.actions.cancel') }}
                        </Button>
                        <Button
                            form="global-team-person"
                            type="submit"
                            :loading="form.processing"
                        >
                            {{ $t('settings.team.actions.save') }}
                        </Button>
                    </div>
                </div>
            </div>
        </div>
    </SettingsLayout>
</template>
