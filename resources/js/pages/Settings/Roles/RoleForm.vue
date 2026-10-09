<script setup>
import { emphasisParts } from '../../../lib/emphasisParts';
import { roleMatchHint } from '../roleMatchHint';
import RolePeopleCard from './RolePeopleCard.vue';
import SettingsLayout from '../../../layouts/SettingsLayout.vue';
import { Button } from '../../../components/ui/button';
import { Card, CardTitle } from '../../../components/ui/card';
import { Checkbox } from '../../../components/ui/checkbox';
import { FormField } from '../../../components/ui/form-field';
import { Icon } from '../../../components/ui/icon';
import { Input } from '../../../components/ui/input';
import { toastFormErrors } from '../../../lib/fieldError';
import { useFlashToast } from '../../../composables/useFlashToast';
import { router, useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import { trans } from 'laravel-vue-i18n';

const props = defineProps({
    role: { type: Object, default: null },
    permissionGroups: { type: Object, required: true },
});
const form = useForm({
    name: props.role?.name ?? '',
    permissions: props.role?.permissions ?? [],
});
const submittedName = ref(null);
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
const title = computed(() =>
    trans(props.role ? 'permissions.edit_role' : 'permissions.new_role'),
);
const breadcrumbs = computed(() => [
    { label: trans('app.name'), href: '/dashboard' },
    { label: trans('nav.settings'), href: '/settings/events' },
    { label: trans('settings.roles.title'), href: '/settings/roles' },
    { label: title.value },
]);
const allPermissions = computed(() =>
    Object.values(props.permissionGroups).flat(),
);
const includedBy = (key) =>
    allPermissions.value.find(
        (permission) =>
            form.permissions.includes(permission.key) &&
            permission.includes.includes(key),
    );
const selected = (key) =>
    form.permissions.includes(key) || Boolean(includedBy(key));
const toggle = (key, checked) => {
    form.permissions = checked
        ? [...new Set([...form.permissions, key])]
        : form.permissions.filter((value) => value !== key);
};
watch(
    () => form.permissions,
    (value) => {
        if (value.length) form.clearErrors('permissions');
    },
);
const { showError, showFormError } = useFlashToast();
const submit = () => {
    submittedName.value = form.name;
    if (!form.permissions.length) {
        form.setError('permissions', trans('permissions.required'));
        return;
    }
    const options = {
        onError: (errors) =>
            toastFormErrors(form, errors, { showError, showFormError }),
    };
    if (props.role) form.put(`/settings/roles/${props.role.id}`, options);
    else form.post('/settings/roles', options);
};
</script>

<template>
    <SettingsLayout
        :title="title"
        :breadcrumbs="breadcrumbs"
        back-href="/settings/roles"
        :back-label="$t('permissions.back_roles')"
    >
        <div class="container mx-auto pb-24">
            <div class="flex items-center justify-between gap-4">
                <h1 class="text-2xl font-bold text-charcoal">{{ title }}</h1>
            </div>
            <p class="mt-1 text-sm text-muted">
                {{ $t('permissions.role_intro') }}
            </p>
            <form
                id="role-form"
                class="mt-5 space-y-4"
                @submit.prevent="submit"
            >
                <Card>
                    <CardTitle class="mb-4">
                        {{ $t('permissions.details') }}
                    </CardTitle>
                    <FormField
                        v-slot="{ id, invalid }"
                        :label="$t('settings.roles.form.name')"
                        :error="form.errors.name"
                        required
                        class="max-w-xl"
                    >
                        <Input
                            :id="id"
                            v-model="form.name"
                            :invalid="invalid"
                            :disabled="form.processing"
                            autofocus
                        />
                    </FormField>
                    <p class="mt-1 text-xs text-muted">
                        {{ $t('permissions.name_hint') }}
                    </p>
                    <p
                        v-if="matchParts.length"
                        class="mt-2 text-sm text-danger"
                    >
                        <template
                            v-for="(part, index) in matchParts"
                            :key="index"
                            ><strong v-if="part.emphasis">{{
                                part.text
                            }}</strong
                            ><template v-else>{{
                                part.text
                            }}</template></template
                        >
                    </p>
                </Card>
                <Card :class="form.errors.permissions && 'border-danger'">
                    <CardTitle>
                        {{ $t('permissions.title') }}
                        <span class="text-danger">*</span>
                    </CardTitle>
                    <p class="mt-1 text-xs text-muted">
                        {{ $t('permissions.help') }}
                    </p>
                    <p
                        v-if="form.errors.permissions"
                        class="mt-2 text-sm text-danger"
                        role="alert"
                    >
                        {{ form.errors.permissions }}
                    </p>
                    <div class="mt-4 grid items-start gap-4 md:grid-cols-3">
                        <div
                            v-for="(permissions, group) in permissionGroups"
                            :key="group"
                            class="rounded-lg border border-line bg-page p-4"
                        >
                            <h3
                                class="mb-3 text-xs font-bold text-muted uppercase"
                            >
                                {{ $t(`permissions.groups.${group}`) }}
                            </h3>
                            <div
                                v-for="permission in permissions"
                                :key="permission.key"
                                class="mb-2 last:mb-0"
                            >
                                <div
                                    class="flex items-center gap-2"
                                    :title="permission.tooltip"
                                >
                                    <Checkbox
                                        :model-value="selected(permission.key)"
                                        :label="permission.label"
                                        :disabled="
                                            Boolean(
                                                includedBy(permission.key),
                                            ) || form.processing
                                        "
                                        @update:model-value="
                                            (checked) =>
                                                toggle(permission.key, checked)
                                        "
                                    />
                                    <Icon
                                        :name="[
                                            'fas',
                                            includedBy(permission.key)
                                                ? 'lock'
                                                : 'circle-info',
                                        ]"
                                        class="text-muted"
                                        size="sm"
                                    />
                                </div>
                                <p
                                    v-if="includedBy(permission.key)"
                                    class="mt-1 ml-6 text-xs text-muted"
                                >
                                    {{
                                        $t('permissions.included', {
                                            permission: includedBy(
                                                permission.key,
                                            ).label,
                                        })
                                    }}
                                </p>
                            </div>
                        </div>
                    </div>
                </Card>
            </form>
            <RolePeopleCard
                v-if="role"
                :role-id="role.id"
            />
        </div>
        <div
            class="fixed right-0 bottom-0 left-0 z-30 border-t border-line bg-ground lg:left-[var(--app-sidebar-width)]"
        >
            <div class="content-body container mx-auto">
                <div class="mx-auto flex items-center justify-between py-4">
                    <Button
                        variant="outline"
                        @click="router.visit('/settings/roles')"
                        >{{ $t('actions.cancel') }}</Button
                    >
                    <Button
                        type="submit"
                        form="role-form"
                        :disabled="form.processing"
                        >{{ $t('actions.save') }}</Button
                    >
                </div>
            </div>
        </div>
    </SettingsLayout>
</template>
