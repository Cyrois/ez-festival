<script setup>
import AppLayout from '../../layouts/AppLayout.vue';
import { Button } from '../../components/ui/button';
import { Card } from '../../components/ui/card';
import { Checkbox } from '../../components/ui/checkbox';
import { FormField } from '../../components/ui/form-field';
import { Icon } from '../../components/ui/icon';
import { Input } from '../../components/ui/input';
import { LabelCombobox } from '../../components/ui/label-combobox';
import { Select } from '../../components/ui/select';
import { Textarea } from '../../components/ui/textarea';
import { useFlashToast } from '../../composables/useFlashToast';
import { useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import { trans } from 'laravel-vue-i18n';

const props = defineProps({
    event: { type: Object, required: true },
    labels: { type: Array, default: () => [] },
    labelColors: { type: Array, required: true },
    entitlementItems: { type: Array, default: () => [] },
    customFields: { type: Array, default: () => [] },
    pass: { type: Object, default: null },
});

const form = useForm({
    name: props.pass?.name ?? '',
    max_assignments: props.pass?.max_assignments ?? '',
    label_ids: props.pass?.label_ids ?? [],
    new_labels: [],
    entitlement_item_ids: props.pass?.entitlement_item_ids ?? [],
    custom_fields: Object.fromEntries(
        props.customFields.map((field) => [
            field.id,
            props.pass?.custom_fields?.[field.id] ??
                (field.type === 'checkbox' ? false : ''),
        ]),
    ),
});
const { showFormError } = useFlashToast();
const breadcrumbs = computed(() => [
    { label: trans('nav.credentials'), href: '/credentials/passes' },
    { label: trans('credentials.passes.title'), href: '/credentials/passes' },
    {
        label: props.pass
            ? trans('credentials.passes.edit_crumb')
            : trans('credentials.passes.create_crumb'),
    },
]);

const isEditing = computed(() => props.pass !== null);
const pageTitle = computed(() =>
    isEditing.value
        ? trans('credentials.passes.edit_title')
        : trans('credentials.passes.create'),
);
const labelError = computed(
    () =>
        form.errors.label_ids ||
        Object.entries(form.errors).find(([key]) =>
            key.startsWith('new_labels.'),
        )?.[1] ||
        '',
);

const addEntitlement = () => {
    if (props.entitlementItems[0]) {
        form.entitlement_item_ids.push(props.entitlementItems[0].id);
    }
};

const removeEntitlement = (index) => {
    form.entitlement_item_ids.splice(index, 1);
};

const submit = () => {
    const options = { onError: showFormError };

    if (isEditing.value) {
        form.put(
            `/events/${props.event.id}/credentials/passes/${props.pass.id}`,
            options,
        );
        return;
    }

    form.post(`/events/${props.event.id}/credentials/passes`, options);
};
</script>

<template>
    <AppLayout
        :title="pageTitle"
        :breadcrumbs="breadcrumbs"
        back-href="/credentials/passes"
        :back-label="$t('credentials.passes.back')"
    >
        <div class="w-full">
            <div class="mb-6">
                <h1 class="m-0 text-2xl font-bold tracking-tight">
                    {{ pageTitle }}
                </h1>
                <p class="mt-1 mb-0 text-sm leading-5 text-muted">
                    {{
                        isEditing
                            ? $t('credentials.passes.edit_lead')
                            : $t('credentials.passes.create_lead')
                    }}
                </p>
            </div>

            <form @submit.prevent="submit">
                <Card class="p-5">
                    <section>
                        <h2 class="m-0 text-lg font-semibold text-muted">
                            {{ $t('credentials.passes.details.title') }}
                        </h2>
                        <p class="mt-1 mb-4 text-xs text-muted">
                            {{ $t('credentials.passes.details.lead') }}
                        </p>
                        <div class="space-y-4">
                            <FormField
                                v-slot="{ id, invalid }"
                                :label="$t('credentials.passes.fields.name')"
                                :error="form.errors.name"
                                required
                            >
                                <Input
                                    :id="id"
                                    v-model="form.name"
                                    :invalid="invalid"
                                    :placeholder="
                                        $t(
                                            'credentials.passes.fields.name_placeholder',
                                        )
                                    "
                                    maxlength="255"
                                    required
                                    autofocus
                                />
                            </FormField>
                            <FormField
                                v-slot="{ id, invalid }"
                                :label="
                                    $t(
                                        'credentials.passes.fields.max_assignments',
                                    )
                                "
                                :error="form.errors.max_assignments"
                                :hint="
                                    $t(
                                        'credentials.passes.fields.max_assignments_hint',
                                    )
                                "
                            >
                                <Input
                                    :id="id"
                                    v-model="form.max_assignments"
                                    :invalid="invalid"
                                    :placeholder="
                                        $t(
                                            'credentials.passes.fields.max_assignments_placeholder',
                                        )
                                    "
                                    type="number"
                                    min="1"
                                    step="1"
                                />
                            </FormField>
                        </div>
                    </section>

                    <section class="mt-5 border-t border-line pt-5">
                        <h2 class="m-0 text-lg font-semibold text-muted">
                            {{ $t('credentials.passes.labels.title') }}
                        </h2>
                        <p class="mt-1 mb-4 text-xs text-muted">
                            {{ $t('credentials.passes.labels.lead') }}
                        </p>
                        <LabelCombobox
                            v-model="form.label_ids"
                            v-model:new-labels="form.new_labels"
                            :labels="labels"
                            :colors="labelColors"
                            :invalid="Boolean(labelError)"
                            :disabled="form.processing"
                            allow-create
                        />
                        <p
                            v-if="labelError"
                            class="mt-2 mb-0 text-xs text-danger"
                            role="alert"
                        >
                            {{ labelError }}
                        </p>
                    </section>

                    <section class="mt-5 border-t border-line pt-5">
                        <h2 class="m-0 text-lg font-semibold text-muted">
                            {{ $t('credentials.passes.custom_fields.title') }}
                        </h2>
                        <p class="mt-1 mb-4 text-xs text-muted">
                            {{ $t('credentials.passes.custom_fields.lead') }}
                        </p>
                        <div
                            v-if="customFields.length > 0"
                            class="grid grid-cols-1 gap-4"
                        >
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
                                        form.errors[`custom_fields.${field.id}`]
                                    "
                                    :required="field.required"
                                >
                                    <template #default="{ id, invalid }">
                                        <Input
                                            :id="id"
                                            v-model="
                                                form.custom_fields[field.id]
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
                                        form.errors[`custom_fields.${field.id}`]
                                    "
                                    :required="field.required"
                                >
                                    <template #default="{ id, invalid }">
                                        <Textarea
                                            :id="id"
                                            v-model="
                                                form.custom_fields[field.id]
                                            "
                                            :invalid="invalid"
                                        />
                                    </template>
                                </FormField>
                                <FormField
                                    v-else-if="field.type === 'select'"
                                    :label="field.label"
                                    :error="
                                        form.errors[`custom_fields.${field.id}`]
                                    "
                                    :required="field.required"
                                >
                                    <template #default="{ id, invalid }">
                                        <Select
                                            :id="id"
                                            v-model="
                                                form.custom_fields[field.id]
                                            "
                                            :invalid="invalid"
                                        >
                                            <option value="">
                                                {{
                                                    $t('ui.select.placeholder')
                                                }}
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
                                        form.errors[`custom_fields.${field.id}`]
                                    "
                                    :required="field.required"
                                >
                                    <template #default="{ id, invalid }">
                                        <Checkbox
                                            :id="id"
                                            v-model="
                                                form.custom_fields[field.id]
                                            "
                                            :invalid="invalid"
                                        />
                                    </template>
                                </FormField>
                            </template>
                        </div>
                        <p class="mt-3 mb-0 text-xs text-muted">
                            {{ $t('credentials.passes.custom_fields.note') }}
                        </p>
                    </section>

                    <section class="mt-5 border-t border-line pt-5">
                        <h2 class="m-0 text-lg font-semibold text-muted">
                            {{ $t('credentials.passes.entitlements.title') }}
                        </h2>
                        <p class="mt-1 mb-3 text-xs text-muted">
                            {{
                                $t(
                                    'credentials.passes.entitlements.description',
                                )
                            }}
                        </p>
                        <div class="space-y-2">
                            <div
                                v-for="(
                                    itemId, index
                                ) in form.entitlement_item_ids"
                                :key="`${itemId}-${index}`"
                                class="flex items-center gap-2"
                            >
                                <Select
                                    v-model="form.entitlement_item_ids[index]"
                                    class="flex-1"
                                    :aria-label="
                                        $t(
                                            'credentials.passes.entitlements.item',
                                        )
                                    "
                                >
                                    <option
                                        v-for="item in entitlementItems"
                                        :key="item.id"
                                        :value="item.id"
                                    >
                                        {{ item.name }}
                                    </option>
                                </Select>
                                <Button
                                    type="button"
                                    size="sm"
                                    variant="ghost"
                                    @click="removeEntitlement(index)"
                                >
                                    {{
                                        $t(
                                            'credentials.passes.entitlements.remove',
                                        )
                                    }}
                                </Button>
                            </div>
                            <p
                                v-if="form.entitlement_item_ids.length === 0"
                                class="m-0 text-sm text-muted"
                            >
                                {{
                                    $t('credentials.passes.entitlements.empty')
                                }}
                            </p>
                        </div>
                        <Button
                            v-if="entitlementItems.length > 0"
                            type="button"
                            size="sm"
                            variant="secondary"
                            class="mt-3"
                            @click="addEntitlement"
                        >
                            {{ $t('credentials.passes.entitlements.add') }}
                        </Button>
                    </section>

                    <section class="mt-5 border-t border-line pt-5">
                        <h2 class="m-0 text-lg font-semibold text-muted">
                            {{ $t('credentials.passes.products.title') }}
                        </h2>
                        <div
                            class="mt-2 rounded-xl border border-dashed border-line bg-page px-6 py-5 text-center"
                        >
                            <p class="m-0 text-xs font-bold text-muted">
                                {{ $t('credentials.passes.coming_later') }}
                            </p>
                            <p class="mt-1 mb-0 text-xs text-muted">
                                {{
                                    $t(
                                        'credentials.passes.products.description',
                                    )
                                }}
                            </p>
                        </div>
                    </section>

                    <div
                        class="mt-5 flex justify-end gap-2 border-t border-line pt-5"
                    >
                        <Button
                            href="/credentials/passes"
                            variant="ghost"
                            :disabled="form.processing"
                        >
                            {{ $t('setup.actions.cancel') }}
                        </Button>
                        <Button
                            type="submit"
                            :loading="form.processing"
                        >
                            {{
                                isEditing
                                    ? $t('credentials.passes.actions.save')
                                    : $t('credentials.passes.actions.create')
                            }}
                        </Button>
                    </div>
                </Card>
            </form>
        </div>
    </AppLayout>
</template>
