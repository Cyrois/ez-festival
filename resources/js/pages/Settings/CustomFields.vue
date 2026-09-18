<script setup>
import SettingsLayout from '../../layouts/SettingsLayout.vue';
import { Button } from '../../components/ui/button';
import { Card } from '../../components/ui/card';
import { Checkbox } from '../../components/ui/checkbox';
import { FormField } from '../../components/ui/form-field';
import { Icon } from '../../components/ui/icon';
import { Input } from '../../components/ui/input';
import { Select } from '../../components/ui/select';
import { Textarea } from '../../components/ui/textarea';
import { useFlashToast } from '../../composables/useFlashToast';
import { fieldError, toastFormErrors } from '../../lib/fieldError';
import { router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { trans } from 'laravel-vue-i18n';

const props = defineProps({
    fields: { type: Array, default: () => [] },
});

const breadcrumbs = computed(() => [
    { label: trans('app.name'), href: '/dashboard' },
    { label: trans('nav.settings'), href: '/settings/events' },
    { label: trans('settings.custom_fields.title') },
]);

const targets = ['artist', 'vendor', 'patron', 'team_member', 'user'];

const { showError, showFormError, showSuccess } = useFlashToast();
const editing = ref(null);
const form = useForm({
    target: 'user',
    label: '',
    type: 'text',
    required: false,
    active: true,
    optionsText: '',
});

const isSelect = computed(() => form.type === 'select');
const editingTarget = computed(() =>
    editing.value === 'new' ? form.target : editing.value?.target,
);

const fieldsForTarget = (target) =>
    props.fields.filter((field) => field.target === target);

const resetForm = () => {
    editing.value = null;
    form.reset();
    form.target = 'user';
    form.type = 'text';
    form.required = false;
    form.active = true;
    form.optionsText = '';
    form.clearErrors();
};

const beginCreate = (target) => {
    resetForm();
    form.target = target;
    editing.value = 'new';
};

const beginEdit = (field) => {
    editing.value = field;
    form.target = field.target;
    form.label = field.label;
    form.type = field.type;
    form.required = field.required;
    form.active = field.active;
    form.optionsText = (field.options ?? []).join('\n');
    form.clearErrors();
};

const submit = () => {
    form.transform((data) => ({
        target: data.target,
        label: data.label,
        type: data.type,
        required: data.required,
        active: data.active,
        options:
            data.type === 'select'
                ? data.optionsText
                      .split('\n')
                      .map((option) => option.trim())
                      .filter(Boolean)
                : [],
    }));

    const options = {
        preserveScroll: true,
        onSuccess: () => {
            const toast = editing.value === 'new' ? 'created' : 'updated';
            resetForm();
            showSuccess(trans(`settings.custom_fields.toast.${toast}`));
        },
        onError: (errors) =>
            toastFormErrors(form, errors, { showError, showFormError }),
    };

    if (editing.value === 'new') {
        form.post('/settings/custom-fields', options);
        return;
    }

    form.put(`/settings/custom-fields/${editing.value.id}`, options);
};

const remove = (field) => {
    router.delete(`/settings/custom-fields/${field.id}`, {
        preserveScroll: true,
        onSuccess: () =>
            showSuccess(trans('settings.custom_fields.toast.deleted')),
        onError: (errors) => showFormError(errors),
    });
};
</script>

<template>
    <SettingsLayout
        :title="$t('settings.custom_fields.title')"
        :breadcrumbs="breadcrumbs"
    >
        <div class="flex flex-col gap-8">
            <div>
                <h1 class="m-0 text-2xl font-bold tracking-tight">
                    {{ $t('settings.custom_fields.title') }}
                </h1>
                <p class="mt-1 mb-0 text-sm text-muted">
                    {{ $t('settings.custom_fields.lead') }}
                </p>
            </div>

            <section
                v-for="(target, index) in targets"
                :key="target"
                :class="[
                    'flex flex-col gap-4',
                    index > 0 && 'border-t border-line pt-8',
                ]"
            >
                <div class="flex items-center justify-between gap-4">
                    <h2 class="m-0 text-lg font-semibold text-charcoal">
                        {{ $t(`settings.custom_fields.target.${target}`) }}
                    </h2>
                    <Button
                        v-if="editing === null"
                        type="button"
                        size="sm"
                        @click="beginCreate(target)"
                    >
                        <Icon
                            :name="['fas', 'plus']"
                            size="sm"
                            class="mr-1.5"
                        />
                        {{
                            $t('settings.custom_fields.actions.add_target', {
                                target: $t(
                                    `settings.custom_fields.target_singular.${target}`,
                                ),
                            })
                        }}
                    </Button>
                </div>

                <Transition
                    enter-active-class="transition duration-200 ease-out"
                    enter-from-class="-translate-y-3 opacity-0"
                    enter-to-class="translate-y-0 opacity-100"
                    leave-active-class="transition duration-150 ease-in"
                    leave-from-class="translate-y-0 opacity-100"
                    leave-to-class="-translate-y-3 opacity-0"
                >
                    <Card v-if="editingTarget === target">
                        <template #header>
                            <h3
                                class="m-0 text-base font-semibold text-charcoal"
                            >
                                {{
                                    editing === 'new'
                                        ? $t(
                                              'settings.custom_fields.form.title.add_target',
                                              {
                                                  target: $t(
                                                      `settings.custom_fields.target_singular.${target}`,
                                                  ),
                                              },
                                          )
                                        : $t(
                                              'settings.custom_fields.form.title.edit',
                                          )
                                }}
                            </h3>
                        </template>

                        <form @submit.prevent="submit">
                            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                <FormField
                                    :label="
                                        $t('settings.custom_fields.form.label')
                                    "
                                    :error="fieldError(form, 'label')"
                                    required
                                >
                                    <template #default="{ id, invalid }">
                                        <Input
                                            :id="id"
                                            v-model="form.label"
                                            type="text"
                                            :invalid="invalid"
                                            autocomplete="off"
                                        />
                                    </template>
                                </FormField>
                                <FormField
                                    :label="
                                        $t('settings.custom_fields.form.type')
                                    "
                                    :error="fieldError(form, 'type')"
                                    required
                                >
                                    <template #default="{ id, invalid }">
                                        <Select
                                            :id="id"
                                            v-model="form.type"
                                            :invalid="invalid"
                                        >
                                            <option value="text">
                                                {{
                                                    $t(
                                                        'settings.custom_fields.type.text',
                                                    )
                                                }}
                                            </option>
                                            <option value="textarea">
                                                {{
                                                    $t(
                                                        'settings.custom_fields.type.textarea',
                                                    )
                                                }}
                                            </option>
                                            <option value="number">
                                                {{
                                                    $t(
                                                        'settings.custom_fields.type.number',
                                                    )
                                                }}
                                            </option>
                                            <option value="date">
                                                {{
                                                    $t(
                                                        'settings.custom_fields.type.date',
                                                    )
                                                }}
                                            </option>
                                            <option value="select">
                                                {{
                                                    $t(
                                                        'settings.custom_fields.type.select',
                                                    )
                                                }}
                                            </option>
                                            <option value="checkbox">
                                                {{
                                                    $t(
                                                        'settings.custom_fields.type.checkbox',
                                                    )
                                                }}
                                            </option>
                                        </Select>
                                    </template>
                                </FormField>
                                <FormField
                                    v-if="isSelect"
                                    :label="
                                        $t(
                                            'settings.custom_fields.form.options',
                                        )
                                    "
                                    :error="fieldError(form, 'options')"
                                    :hint="
                                        $t(
                                            'settings.custom_fields.form.options_hint',
                                        )
                                    "
                                    required
                                    class="sm:col-span-2"
                                >
                                    <template #default="{ id, invalid }">
                                        <Textarea
                                            :id="id"
                                            v-model="form.optionsText"
                                            :invalid="invalid"
                                        />
                                    </template>
                                </FormField>
                            </div>

                            <div
                                class="mt-4 flex flex-col gap-3 sm:flex-row sm:items-center"
                            >
                                <Checkbox
                                    v-model="form.required"
                                    :label="
                                        $t(
                                            'settings.custom_fields.form.required',
                                        )
                                    "
                                />
                                <Checkbox
                                    v-if="editing !== 'new'"
                                    v-model="form.active"
                                    :label="
                                        $t('settings.custom_fields.form.active')
                                    "
                                />
                                <div class="flex gap-2 sm:ml-auto">
                                    <Button
                                        type="button"
                                        variant="outline"
                                        :disabled="form.processing"
                                        @click="resetForm"
                                    >
                                        {{
                                            $t(
                                                'settings.custom_fields.actions.cancel',
                                            )
                                        }}
                                    </Button>
                                    <Button
                                        type="submit"
                                        :loading="form.processing"
                                        :disabled="form.processing"
                                    >
                                        {{
                                            editing === 'new'
                                                ? $t(
                                                      'settings.custom_fields.actions.save',
                                                  )
                                                : $t(
                                                      'settings.custom_fields.actions.update',
                                                  )
                                        }}
                                    </Button>
                                </div>
                            </div>
                        </form>
                    </Card>
                </Transition>

                <div
                    v-if="fieldsForTarget(target).length > 0"
                    class="flex flex-col gap-3"
                >
                    <Card
                        v-for="field in fieldsForTarget(target)"
                        :key="field.id"
                        class="p-4"
                    >
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <div class="flex flex-wrap items-center gap-2">
                                    <h3 class="m-0 text-base font-semibold">
                                        {{ field.label }}
                                    </h3>
                                    <span
                                        class="rounded-full bg-page px-2 py-0.5 text-xs text-muted"
                                    >
                                        {{
                                            $t(
                                                `settings.custom_fields.type.${field.type}`,
                                            )
                                        }}
                                    </span>
                                    <span
                                        v-if="field.required"
                                        class="rounded-full bg-primary/10 px-2 py-0.5 text-xs text-primary"
                                    >
                                        {{
                                            $t(
                                                'settings.custom_fields.status.required',
                                            )
                                        }}
                                    </span>
                                    <span
                                        v-if="!field.active"
                                        class="rounded-full bg-page px-2 py-0.5 text-xs text-muted"
                                    >
                                        {{
                                            $t(
                                                'settings.custom_fields.status.inactive',
                                            )
                                        }}
                                    </span>
                                </div>
                                <p
                                    v-if="field.type === 'select'"
                                    class="mt-1 mb-0 text-sm text-muted"
                                >
                                    {{ (field.options ?? []).join(', ') }}
                                </p>
                            </div>
                            <div class="flex shrink-0 gap-2">
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    :aria-label="
                                        $t(
                                            'settings.custom_fields.actions.edit',
                                        )
                                    "
                                    @click="beginEdit(field)"
                                >
                                    <Icon :name="['fas', 'pencil']" />
                                </Button>
                                <Button
                                    type="button"
                                    variant="danger"
                                    size="sm"
                                    :aria-label="
                                        $t(
                                            'settings.custom_fields.actions.delete',
                                        )
                                    "
                                    @click="remove(field)"
                                >
                                    <Icon :name="['fas', 'trash']" />
                                </Button>
                            </div>
                        </div>
                    </Card>
                </div>
                <p
                    v-else-if="editingTarget !== target"
                    class="m-0 text-sm text-muted"
                >
                    {{ $t('settings.custom_fields.empty.section') }}
                </p>
            </section>
        </div>
    </SettingsLayout>
</template>
