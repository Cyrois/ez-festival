<script setup>
import SettingsLayout from '../../../layouts/SettingsLayout.vue';
import { Button } from '../../../components/ui/button';
import { Card, CardTitle } from '../../../components/ui/card';
import { CustomDropdown } from '../../../components/ui/custom-dropdown';
import { Dialog } from '../../../components/ui/dialog';
import { FormField } from '../../../components/ui/form-field';
import { Icon } from '../../../components/ui/icon';
import { Input } from '../../../components/ui/input';
import { Switch } from '../../../components/ui/switch';
import { toastFormErrors } from '../../../lib/fieldError';
import { useFlashToast } from '../../../composables/useFlashToast';
import { Link, router, useForm } from '@inertiajs/vue3';
import { computed, onUnmounted, reactive, ref, watch } from 'vue';
import { trans } from 'laravel-vue-i18n';

const props = defineProps({
    events: { type: Array, required: true },
    roles: { type: Array, required: true },
    statuses: { type: Array, required: true },
});

const form = useForm({
    name: '',
    email: '',
    phone: '',
    can_log_in: true,
    status: 'applied',
    event_access: [],
});
const selectedRoles = reactive(
    Object.fromEntries(props.events.map((event) => [event.id, ''])),
);
const lookup = ref(null);
const lookupBusy = ref(false);
const duplicateOpen = ref(false);
let lookupTimer;

const roleItems = computed(() => [
    { value: '', title: trans('settings.team.no_access') },
    ...props.roles.map((role) => ({ value: role.id, title: role.name })),
]);
const statusItems = computed(() =>
    props.statuses.map((status) => ({
        value: status,
        title: trans(`team.advancement.status.${status}`),
    })),
);
const breadcrumbs = computed(() => [
    { label: trans('app.name'), href: '/dashboard' },
    { label: trans('nav.settings'), href: '/settings/events' },
    { label: trans('settings.team.title'), href: '/settings/team' },
    { label: trans('settings.team.add.title') },
]);
const { showError, showFormError } = useFlashToast();

const checkEmail = async () => {
    lookup.value = null;
    const email = form.email.trim();
    if (!email || !email.includes('@')) {
        lookupBusy.value = false;
        return;
    }

    try {
        const response = await fetch(
            `/settings/team/email-lookup?email=${encodeURIComponent(email)}`,
            { headers: { Accept: 'application/json' } },
        );
        if (!response.ok) return;
        lookup.value = (await response.json()).data;
        duplicateOpen.value = lookup.value.on_global_team;
    } catch {
        lookup.value = null;
    } finally {
        lookupBusy.value = false;
    }
};

watch(
    () => form.email,
    () => {
        window.clearTimeout(lookupTimer);
        lookupBusy.value = Boolean(form.email.trim());
        lookupTimer = window.setTimeout(checkEmail, 350);
    },
);
onUnmounted(() => window.clearTimeout(lookupTimer));

const submit = () => {
    if (lookupBusy.value || lookup.value?.exists) return;

    form.event_access = props.events
        .filter((event) => !event.locked && selectedRoles[event.id] !== '')
        .map((event) => ({
            event_id: event.id,
            role_id: selectedRoles[event.id],
        }));

    form.post('/settings/team', {
        onError: (errors) =>
            toastFormErrors(form, errors, { showError, showFormError }),
    });
};
</script>

<template>
    <SettingsLayout
        :title="$t('settings.team.add.title')"
        :breadcrumbs="breadcrumbs"
    >
        <div class="container mx-auto pb-24">
            <header class="mb-5">
                <h1 class="m-0 text-2xl font-bold tracking-tight">
                    {{ $t('settings.team.add.title') }}
                </h1>
                <p class="mt-1 mb-0 text-sm text-muted">
                    {{ $t('settings.team.add.lead') }}
                </p>
            </header>

            <form
                id="global-team-add"
                class="space-y-4"
                @submit.prevent="submit"
            >
                <div class="grid gap-4 lg:grid-cols-2">
                    <Card>
                        <CardTitle class="mb-4">
                            {{ $t('settings.team.details') }}
                        </CardTitle>
                        <div class="grid gap-4">
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
                            <FormField
                                v-slot="{ id, invalid }"
                                :label="$t('settings.team.fields.email')"
                                :error="form.errors.email"
                                required
                            >
                                <Input
                                    :id="id"
                                    v-model="form.email"
                                    type="email"
                                    :invalid="invalid"
                                    maxlength="255"
                                    required
                                />
                                <p
                                    v-if="
                                        lookup?.exists && !lookup.on_global_team
                                    "
                                    class="mt-1.5 mb-0 text-xs text-warning"
                                    role="alert"
                                >
                                    {{
                                        $t(
                                            'settings.team.add.existing_contact',
                                            {
                                                name: lookup.person.name,
                                            },
                                        )
                                    }}
                                    <Link
                                        :href="`/settings/team/${lookup.person.id}`"
                                        class="font-semibold text-secondary underline underline-offset-2"
                                    >
                                        {{
                                            $t(
                                                'settings.team.add.open_existing',
                                            )
                                        }}
                                    </Link>
                                </p>
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
                    </Card>

                    <Card>
                        <CardTitle>
                            {{ $t('settings.team.security') }}
                        </CardTitle>
                        <p class="mt-1 mb-4 text-xs text-muted">
                            {{ $t('settings.team.security_add_hint') }}
                        </p>
                        <Switch
                            v-model="form.can_log_in"
                            :disabled="form.processing"
                        >
                            {{ $t('settings.team.login.label') }}
                        </Switch>
                        <p class="mt-1.5 mb-0 text-xs text-muted">
                            {{ $t('settings.team.login.add_hint') }}
                        </p>
                    </Card>
                </div>

                <Card>
                    <CardTitle>
                        {{ $t('settings.team.access.title') }}
                    </CardTitle>
                    <p class="mt-1 mb-4 text-xs text-muted">
                        {{ $t('settings.team.add.access_hint') }}
                    </p>

                    <div class="mb-4 rounded-lg border border-line bg-page p-4">
                        <FormField
                            v-slot="{ id, invalid }"
                            :label="$t('settings.team.fields.status')"
                            :error="form.errors.status"
                            :hint="$t('settings.team.add.status_hint')"
                        >
                            <CustomDropdown
                                :id="id"
                                v-model="form.status"
                                :items="statusItems"
                                :invalid="invalid"
                            />
                        </FormField>
                    </div>

                    <p
                        v-if="form.errors.event_access"
                        class="mb-3 text-sm text-danger"
                        role="alert"
                    >
                        {{ form.errors.event_access }}
                    </p>
                    <div class="overflow-visible rounded-lg border border-line">
                        <div
                            v-for="event in events"
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
                                    v-model="selectedRoles[event.id]"
                                    :items="roleItems"
                                    :disabled="event.locked || form.processing"
                                />
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
                </Card>
            </form>

            <div
                class="fixed right-0 bottom-0 left-0 z-30 border-t border-line bg-ground/95 py-3 backdrop-blur lg:left-[var(--app-sidebar-width)]"
            >
                <div class="container mx-auto px-4 md:px-6">
                    <div class="flex items-center justify-between">
                        <Button
                            href="/settings/team"
                            variant="cancel"
                            :disabled="form.processing"
                        >
                            {{ $t('setup.actions.cancel') }}
                        </Button>
                        <Button
                            form="global-team-add"
                            type="submit"
                            :loading="form.processing"
                            :disabled="
                                form.processing || lookupBusy || lookup?.exists
                            "
                        >
                            <Icon
                                :name="['fas', 'plus']"
                                size="sm"
                            />
                            {{ $t('settings.team.actions.add') }}
                        </Button>
                    </div>
                </div>
            </div>
        </div>

        <Dialog
            :open="duplicateOpen"
            :title="$t('settings.team.add.duplicate_title')"
            :description="$t('settings.team.add.duplicate_body')"
            :confirm-label="
                $t('settings.team.add.open_person', {
                    name: lookup?.person?.name ?? '',
                })
            "
            :show-cancel="false"
            confirm-variant="primary"
            @update:open="duplicateOpen = $event"
            @confirm="router.get(`/settings/team/${lookup.person.id}`)"
        />
    </SettingsLayout>
</template>
