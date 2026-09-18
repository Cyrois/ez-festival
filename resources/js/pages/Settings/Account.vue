<script setup>
import SettingsLayout from '../../layouts/SettingsLayout.vue';
import { Button } from '../../components/ui/button';
import { Card } from '../../components/ui/card';
import { Checkbox } from '../../components/ui/checkbox';
import { FormField } from '../../components/ui/form-field';
import { Input } from '../../components/ui/input';
import { Select } from '../../components/ui/select';
import { Textarea } from '../../components/ui/textarea';
import { useFlashToast } from '../../composables/useFlashToast';
import { fieldError, toastFormErrors } from '../../lib/fieldError';
import { useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import { trans } from 'laravel-vue-i18n';

const props = defineProps({
    account: {
        type: Object,
        required: true,
    },
    customFields: {
        type: Array,
        default: () => [],
    },
});

const breadcrumbs = computed(() => [
    { label: trans('app.name'), href: '/dashboard' },
    { label: trans('nav.settings'), href: '/settings/events' },
    { label: trans('settings.account.title') },
]);

const { showError, showSuccess, showFormError } = useFlashToast();

const accountForm = useForm({
    name: props.account.name ?? '',
    email: props.account.email ?? '',
    phone: props.account.phone ?? '',
    custom_fields: Object.fromEntries(
        props.customFields.map((field) => [
            field.id,
            field.value ?? (field.type === 'checkbox' ? false : ''),
        ]),
    ),
});

const passwordForm = useForm({
    current_password: '',
    password: '',
    password_confirmation: '',
});

const submitAccount = () => {
    accountForm.put('/settings/account', {
        preserveScroll: true,
        onSuccess: () => showSuccess(trans('settings.account.toast.updated')),
        onError: (errors) =>
            toastFormErrors(accountForm, errors, { showError, showFormError }),
    });
};

const submitPassword = () => {
    passwordForm.put('/settings/account/password', {
        preserveScroll: true,
        onSuccess: () => {
            passwordForm.reset();
            showSuccess(trans('settings.account.toast.password_updated'));
        },
        onError: (errors) =>
            toastFormErrors(passwordForm, errors, {
                showError,
                showFormError,
            }),
    });
};
</script>

<template>
    <SettingsLayout
        :title="$t('settings.account.title')"
        :breadcrumbs="breadcrumbs"
    >
        <div class="flex flex-col gap-6">
            <div>
                <h1 class="m-0 text-2xl font-bold tracking-tight">
                    {{ $t('settings.account.title') }}
                </h1>
                <p class="mt-1 mb-0 text-sm text-muted">
                    {{ $t('settings.account.lead') }}
                </p>
            </div>

            <Card>
                <template #header>
                    <h2 class="m-0 text-base font-semibold text-charcoal">
                        {{ $t('settings.account.profile.title') }}
                    </h2>
                    <p class="mt-1 mb-0 text-sm text-muted">
                        {{ $t('settings.account.profile.lead') }}
                    </p>
                </template>

                <form @submit.prevent="submitAccount">
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <FormField
                            :label="$t('settings.account.fields.name')"
                            :error="fieldError(accountForm, 'name')"
                            required
                        >
                            <template #default="{ id, invalid }">
                                <Input
                                    :id="id"
                                    v-model="accountForm.name"
                                    type="text"
                                    :invalid="invalid"
                                    autocomplete="name"
                                />
                            </template>
                        </FormField>
                        <FormField
                            :label="$t('settings.account.fields.email')"
                            :error="fieldError(accountForm, 'email')"
                            required
                        >
                            <template #default="{ id, invalid }">
                                <Input
                                    :id="id"
                                    v-model="accountForm.email"
                                    type="email"
                                    :invalid="invalid"
                                    autocomplete="email"
                                />
                            </template>
                        </FormField>
                        <FormField
                            :label="$t('settings.account.fields.phone')"
                            :error="fieldError(accountForm, 'phone')"
                        >
                            <template #default="{ id, invalid }">
                                <Input
                                    :id="id"
                                    v-model="accountForm.phone"
                                    type="tel"
                                    :invalid="invalid"
                                    autocomplete="tel"
                                />
                            </template>
                        </FormField>
                    </div>

                    <div class="mt-6 flex justify-end">
                        <Button
                            type="submit"
                            :loading="accountForm.processing"
                            :disabled="accountForm.processing"
                        >
                            {{ $t('settings.account.actions.save_profile') }}
                        </Button>
                    </div>
                </form>
            </Card>

            <Card v-if="customFields.length > 0">
                <template #header>
                    <h2 class="m-0 text-base font-semibold text-charcoal">
                        {{ $t('settings.account.custom_fields.title') }}
                    </h2>
                    <p class="mt-1 mb-0 text-sm text-muted">
                        {{ $t('settings.account.custom_fields.lead') }}
                    </p>
                </template>

                <form @submit.prevent="submitAccount">
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <template
                            v-for="field in customFields"
                            :key="field.id"
                        >
                            <FormField
                                v-if="
                                    field.type === 'text' ||
                                    field.type === 'number' ||
                                    field.type === 'date'
                                "
                                :label="field.label"
                                :error="
                                    fieldError(
                                        accountForm,
                                        `custom_fields.${field.id}`,
                                    )
                                "
                                :required="field.required"
                            >
                                <template #default="{ id, invalid }">
                                    <Input
                                        :id="id"
                                        v-model="
                                            accountForm.custom_fields[field.id]
                                        "
                                        :type="
                                            field.type === 'text'
                                                ? 'text'
                                                : field.type
                                        "
                                        :invalid="invalid"
                                        autocomplete="off"
                                    />
                                </template>
                            </FormField>
                            <FormField
                                v-else-if="field.type === 'textarea'"
                                :label="field.label"
                                :error="
                                    fieldError(
                                        accountForm,
                                        `custom_fields.${field.id}`,
                                    )
                                "
                                :required="field.required"
                                class="sm:col-span-2"
                            >
                                <template #default="{ id, invalid }">
                                    <Textarea
                                        :id="id"
                                        v-model="
                                            accountForm.custom_fields[field.id]
                                        "
                                        :invalid="invalid"
                                    />
                                </template>
                            </FormField>
                            <FormField
                                v-else-if="field.type === 'select'"
                                :label="field.label"
                                :error="
                                    fieldError(
                                        accountForm,
                                        `custom_fields.${field.id}`,
                                    )
                                "
                                :required="field.required"
                            >
                                <template #default="{ id, invalid }">
                                    <Select
                                        :id="id"
                                        v-model="
                                            accountForm.custom_fields[field.id]
                                        "
                                        :invalid="invalid"
                                    >
                                        <option value="">
                                            {{ $t('ui.select.placeholder') }}
                                        </option>
                                        <option
                                            v-for="option in field.options"
                                            :key="option"
                                            :value="option"
                                        >
                                            {{ option }}
                                        </option>
                                    </Select>
                                </template>
                            </FormField>
                            <FormField
                                v-else-if="field.type === 'checkbox'"
                                :label="field.label"
                                :error="
                                    fieldError(
                                        accountForm,
                                        `custom_fields.${field.id}`,
                                    )
                                "
                                :required="field.required"
                                class="justify-end"
                            >
                                <template #default="{ id, invalid }">
                                    <Checkbox
                                        :id="id"
                                        v-model="
                                            accountForm.custom_fields[field.id]
                                        "
                                        :invalid="invalid"
                                    />
                                </template>
                            </FormField>
                        </template>
                    </div>

                    <div class="mt-6 flex justify-end">
                        <Button
                            type="submit"
                            :loading="accountForm.processing"
                            :disabled="accountForm.processing"
                        >
                            {{
                                $t(
                                    'settings.account.actions.save_custom_fields',
                                )
                            }}
                        </Button>
                    </div>
                </form>
            </Card>

            <Card>
                <template #header>
                    <h2 class="m-0 text-base font-semibold text-charcoal">
                        {{ $t('settings.account.password.title') }}
                    </h2>
                    <p class="mt-1 mb-0 text-sm text-muted">
                        {{ $t('settings.account.password.lead') }}
                    </p>
                </template>

                <form @submit.prevent="submitPassword">
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                        <FormField
                            :label="
                                $t('settings.account.fields.current_password')
                            "
                            :error="
                                fieldError(passwordForm, 'current_password')
                            "
                            required
                        >
                            <template #default="{ id, invalid }">
                                <Input
                                    :id="id"
                                    v-model="passwordForm.current_password"
                                    type="password"
                                    :invalid="invalid"
                                    autocomplete="current-password"
                                />
                            </template>
                        </FormField>
                        <FormField
                            :label="$t('settings.account.fields.new_password')"
                            :error="fieldError(passwordForm, 'password')"
                            :hint="$t('settings.account.password.hint')"
                            required
                        >
                            <template #default="{ id, invalid }">
                                <Input
                                    :id="id"
                                    v-model="passwordForm.password"
                                    type="password"
                                    :invalid="invalid"
                                    autocomplete="new-password"
                                />
                            </template>
                        </FormField>
                        <FormField
                            :label="
                                $t(
                                    'settings.account.fields.password_confirmation',
                                )
                            "
                            :error="
                                fieldError(
                                    passwordForm,
                                    'password_confirmation',
                                )
                            "
                            required
                        >
                            <template #default="{ id, invalid }">
                                <Input
                                    :id="id"
                                    v-model="passwordForm.password_confirmation"
                                    type="password"
                                    :invalid="invalid"
                                    autocomplete="new-password"
                                />
                            </template>
                        </FormField>
                    </div>

                    <div class="mt-6 flex justify-end">
                        <Button
                            type="submit"
                            :loading="passwordForm.processing"
                            :disabled="passwordForm.processing"
                        >
                            {{ $t('settings.account.actions.update_password') }}
                        </Button>
                    </div>
                </form>
            </Card>
        </div>
    </SettingsLayout>
</template>
