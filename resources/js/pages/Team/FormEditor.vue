<script setup>
import AppLayout from '../../layouts/AppLayout.vue';
import { Button } from '../../components/ui/button';
import { Card } from '../../components/ui/card';
import { Checkbox } from '../../components/ui/checkbox';
import { CustomDropdown } from '../../components/ui/custom-dropdown';
import { FormField } from '../../components/ui/form-field';
import { Icon } from '../../components/ui/icon';
import { Input } from '../../components/ui/input';
import { Textarea } from '../../components/ui/textarea';
import { useFlashToast } from '../../composables/useFlashToast';
import { fieldError, toastFormErrors } from '../../lib/fieldError';
import { useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import { trans } from 'laravel-vue-i18n';

const props = defineProps({
    event: { type: Object, required: true },
    teamForm: { type: Object, default: null },
    statuses: { type: Array, default: () => [] },
    initialFields: { type: Array, required: true },
});

const editing = computed(() => props.teamForm !== null);
const breadcrumbs = computed(() => [
    { label: trans('app.name'), href: '/dashboard' },
    { label: trans('team.title'), href: '/team/advancement' },
    { label: trans('nav.team.forms'), href: '/team/forms' },
    {
        label: trans(
            editing.value
                ? 'team.forms.editor.edit_crumb'
                : 'team.forms.editor.create_crumb',
        ),
    },
]);
const statusItems = computed(() =>
    props.statuses.map((value) => ({
        value,
        title: trans(`team.forms.status.${value}`),
    })),
);
const typeItems = computed(() =>
    ['text', 'textarea', 'select'].map((value) => ({
        value,
        title: trans(`team.forms.field_type.${value}`),
    })),
);
const { showError, showFormError, showSuccess } = useFlashToast();
const form = useForm({
    name: props.teamForm?.name ?? '',
    status: props.teamForm?.status ?? 'draft',
    fields: structuredClone(props.teamForm?.fields ?? props.initialFields),
});

const isBuiltin = (field) =>
    ['name', 'email', 'phone', 'employment_type'].includes(field.key);

const addField = () => {
    form.fields.push({
        id: null,
        key: `custom_${crypto.randomUUID()}`,
        label: '',
        type: 'text',
        required: false,
        options: [],
        optionsText: '',
    });
};

const removeField = (index) => form.fields.splice(index, 1);
const moveField = (index, direction) => {
    const target = index + direction;
    if (target < 0 || target >= form.fields.length) return;
    const [field] = form.fields.splice(index, 1);
    form.fields.splice(target, 0, field);
};
const copyLink = async () => {
    await navigator.clipboard.writeText(props.teamForm.public_url);
    showSuccess(trans('team.forms.toast.link_copied'));
};
const submit = () => {
    form.transform((data) => ({
        ...data,
        fields: data.fields.map((field) => ({
            id: field.id,
            key: field.key,
            label: field.label,
            type: field.type,
            required: field.required,
            options:
                field.type === 'select'
                    ? (field.optionsText ?? field.options.join('\n'))
                          .split('\n')
                          .map((option) => option.trim())
                          .filter(Boolean)
                    : [],
        })),
    }));
    const options = {
        onError: (errors) =>
            toastFormErrors(form, errors, { showError, showFormError }),
    };
    if (editing.value) {
        form.put(`/team/forms/${props.teamForm.id}`, options);
        return;
    }
    form.post(`/team/events/${props.event.id}/forms`, options);
};
</script>

<template>
    <AppLayout
        :title="
            editing
                ? $t('team.forms.editor.edit_title')
                : $t('team.forms.editor.create_title')
        "
        :breadcrumbs="breadcrumbs"
        back-href="/team/forms"
        :back-label="$t('team.forms.actions.back')"
    >
        <form
            class="container mx-auto flex flex-col gap-4"
            @submit.prevent="submit"
        >
            <div>
                <h1 class="m-0 text-2xl font-bold tracking-tight">
                    {{
                        editing
                            ? $t('team.forms.editor.edit_title')
                            : $t('team.forms.editor.create_title')
                    }}
                </h1>
                <p class="mt-1 mb-0 text-sm text-muted">
                    {{ $t('team.forms.editor.lead') }}
                </p>
            </div>
            <Card :title="$t('team.forms.editor.form_card')">
                <div class="flex flex-col gap-4">
                    <FormField
                        :label="$t('team.forms.editor.name')"
                        :error="fieldError(form, 'name')"
                        required
                    >
                        <template #default="{ id, invalid }">
                            <Input
                                :id="id"
                                v-model="form.name"
                                :invalid="invalid"
                            />
                        </template>
                    </FormField>
                    <FormField
                        :label="$t('team.forms.editor.status')"
                        :error="fieldError(form, 'status')"
                        required
                    >
                        <template #default="{ id, invalid }">
                            <CustomDropdown
                                :id="id"
                                v-model="form.status"
                                :items="statusItems"
                                :invalid="invalid"
                            />
                        </template>
                    </FormField>
                    <div
                        v-if="editing && form.status === 'live'"
                        class="flex items-center gap-2 rounded-lg border border-line bg-page p-3"
                    >
                        <code class="min-w-0 flex-1 truncate text-xs">{{
                            teamForm.public_url
                        }}</code>
                        <Button
                            type="button"
                            size="sm"
                            @click="copyLink"
                        >
                            {{ $t('team.forms.actions.copy_link') }}
                        </Button>
                    </div>
                </div>
            </Card>
            <Card>
                <template #header>
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <h2 class="m-0 text-lg font-semibold">
                                {{ $t('team.forms.editor.fields_card') }}
                            </h2>
                            <p class="mt-1 mb-0 text-xs text-muted">
                                {{ $t('team.forms.editor.fields_lead') }}
                            </p>
                        </div>
                        <Button
                            type="button"
                            variant="secondary"
                            size="sm"
                            @click="addField"
                        >
                            <Icon
                                :name="['fas', 'plus']"
                                size="sm"
                                class="mr-2"
                            />
                            {{ $t('team.forms.actions.add_field') }}
                        </Button>
                    </div>
                </template>
                <div class="flex flex-col gap-2">
                    <div
                        v-for="(field, index) in form.fields"
                        :key="field.key"
                        class="rounded-lg border border-line p-3"
                    >
                        <div class="flex flex-wrap items-start gap-3">
                            <div class="flex gap-1 pt-1">
                                <button
                                    type="button"
                                    class="rounded-lg p-2 text-muted hover:bg-page hover:text-charcoal disabled:opacity-30"
                                    :disabled="index === 0"
                                    :aria-label="
                                        $t('team.forms.actions.move_up')
                                    "
                                    @click="moveField(index, -1)"
                                >
                                    <Icon
                                        :name="['fas', 'arrow-up']"
                                        size="sm"
                                    />
                                </button>
                                <button
                                    type="button"
                                    class="rounded-lg p-2 text-muted hover:bg-page hover:text-charcoal disabled:opacity-30"
                                    :disabled="index === form.fields.length - 1"
                                    :aria-label="
                                        $t('team.forms.actions.move_down')
                                    "
                                    @click="moveField(index, 1)"
                                >
                                    <Icon
                                        :name="['fas', 'arrow-down']"
                                        size="sm"
                                    />
                                </button>
                            </div>
                            <div
                                class="grid min-w-64 flex-1 grid-cols-1 gap-3 md:grid-cols-2"
                            >
                                <FormField
                                    :label="$t('team.forms.editor.field_label')"
                                >
                                    <template #default="{ id }">
                                        <Input
                                            :id="id"
                                            v-model="field.label"
                                            :disabled="isBuiltin(field)"
                                        />
                                    </template>
                                </FormField>
                                <FormField
                                    :label="$t('team.forms.editor.field_type')"
                                >
                                    <template #default="{ id }">
                                        <Input
                                            v-if="isBuiltin(field)"
                                            :id="id"
                                            :model-value="
                                                $t(
                                                    `team.forms.field_type.${field.type}`,
                                                )
                                            "
                                            disabled
                                        />
                                        <CustomDropdown
                                            v-else
                                            :id="id"
                                            v-model="field.type"
                                            :items="typeItems"
                                        />
                                    </template>
                                </FormField>
                                <FormField
                                    v-if="
                                        !isBuiltin(field) &&
                                        field.type === 'select'
                                    "
                                    class="md:col-span-2"
                                    :label="$t('team.forms.editor.options')"
                                    :hint="$t('team.forms.editor.options_hint')"
                                >
                                    <template #default="{ id }">
                                        <Textarea
                                            :id="id"
                                            v-model="field.optionsText"
                                            :placeholder="
                                                field.options.join('\n')
                                            "
                                        />
                                    </template>
                                </FormField>
                            </div>
                            <div class="flex items-center gap-3 pt-7">
                                <Checkbox
                                    v-model="field.required"
                                    :disabled="field.key === 'name'"
                                    :label="$t('team.forms.editor.required')"
                                />
                                <Button
                                    v-if="!isBuiltin(field)"
                                    type="button"
                                    variant="ghost"
                                    size="sm"
                                    :aria-label="
                                        $t('team.forms.actions.remove_field')
                                    "
                                    @click="removeField(index)"
                                >
                                    <Icon
                                        :name="['fas', 'trash']"
                                        size="sm"
                                    />
                                </Button>
                            </div>
                        </div>
                    </div>
                </div>
            </Card>
            <div class="flex justify-end gap-3 pt-2">
                <Button
                    href="/team/forms"
                    variant="secondary"
                >
                    {{ $t('labels.cancel') }}
                </Button>
                <Button
                    type="submit"
                    :loading="form.processing"
                >
                    {{ $t('team.forms.actions.save') }}
                </Button>
            </div>
        </form>
    </AppLayout>
</template>
