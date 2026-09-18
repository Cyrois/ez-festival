<script setup>
import AppLayout from '../../layouts/AppLayout.vue';
import { Button } from '../../components/ui/button';
import { Card } from '../../components/ui/card';
import { Checkbox } from '../../components/ui/checkbox';
import { FormField } from '../../components/ui/form-field';
import { Input } from '../../components/ui/input';
import { Select } from '../../components/ui/select';
import { Textarea } from '../../components/ui/textarea';
import { useFlashToast } from '../../composables/useFlashToast';
import { useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import { trans } from 'laravel-vue-i18n';

const props = defineProps({
    event: { type: Object, required: true },
    types: { type: Array, required: true },
    statuses: { type: Array, required: true },
    customFields: { type: Array, default: () => [] },
});

const form = useForm({
    name: '',
    status: 'idea',
    vendor_type_id: '',
    custom_fields: Object.fromEntries(
        props.customFields.map((field) => [
            field.id,
            field.type === 'checkbox' ? false : '',
        ]),
    ),
});
const { showFormError } = useFlashToast();
const breadcrumbs = computed(() => [
    { label: trans('nav.vendors'), href: '/vendors/advancing' },
    { label: trans('vendors.add') },
]);

const submit = () =>
    form.post(`/events/${props.event.id}/vendors`, { onError: showFormError });
</script>

<template>
    <AppLayout
        :title="$t('vendors.add')"
        :breadcrumbs="breadcrumbs"
    >
        <div class="w-full">
            <div class="mb-6">
                <h1 class="m-0 text-2xl font-bold tracking-tight">
                    {{ $t('vendors.add') }}
                </h1>
                <p class="mt-1 mb-0 text-sm text-muted">
                    {{ $t('vendors.create_lead', { name: event.name }) }}
                </p>
            </div>
            <form
                class="flex flex-col gap-6"
                @submit.prevent="submit"
            >
                <Card>
                    <div class="space-y-5">
                        <FormField
                            v-slot="{ id, invalid }"
                            :label="$t('vendors.name')"
                            :error="form.errors.name"
                            :hint="$t('vendors.name_hint')"
                            required
                        >
                            <Input
                                :id="id"
                                v-model="form.name"
                                :invalid="invalid"
                                maxlength="255"
                                required
                                autofocus
                            />
                        </FormField>
                        <div class="grid gap-5 sm:grid-cols-2">
                            <FormField
                                v-slot="{ id, invalid }"
                                :label="$t('vendors.columns.status')"
                                :error="form.errors.status"
                            >
                                <Select
                                    :id="id"
                                    v-model="form.status"
                                    :invalid="invalid"
                                >
                                    <option
                                        v-for="status in statuses"
                                        :key="status"
                                        :value="status"
                                    >
                                        {{ $t(`vendors.status.${status}`) }}
                                    </option>
                                </Select>
                            </FormField>
                            <FormField
                                v-slot="{ id, invalid }"
                                :label="$t('vendors.columns.type')"
                                :error="form.errors.vendor_type_id"
                            >
                                <Select
                                    :id="id"
                                    v-model="form.vendor_type_id"
                                    :invalid="invalid"
                                >
                                    <option value="">
                                        {{ $t('vendors.type_optional') }}
                                    </option>
                                    <option
                                        v-for="type in types"
                                        :key="type.id"
                                        :value="type.id"
                                    >
                                        {{ type.name }}
                                    </option>
                                </Select>
                            </FormField>
                        </div>
                    </div>
                </Card>

                <Card v-if="customFields.length > 0">
                    <template #header>
                        <h2 class="m-0 text-base font-semibold text-charcoal">
                            {{ $t('vendors.custom_fields.title') }}
                        </h2>
                        <p class="mt-1 mb-0 text-sm text-muted">
                            {{ $t('vendors.custom_fields.lead') }}
                        </p>
                    </template>

                    <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
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
                                        v-model="form.custom_fields[field.id]"
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
                                class="sm:col-span-2"
                            >
                                <template #default="{ id, invalid }">
                                    <Textarea
                                        :id="id"
                                        v-model="form.custom_fields[field.id]"
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
                                        v-model="form.custom_fields[field.id]"
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
                                    form.errors[`custom_fields.${field.id}`]
                                "
                                :required="field.required"
                                class="justify-end"
                            >
                                <template #default="{ id, invalid }">
                                    <Checkbox
                                        :id="id"
                                        v-model="form.custom_fields[field.id]"
                                        :invalid="invalid"
                                    />
                                </template>
                            </FormField>
                        </template>
                    </div>
                </Card>

                <div class="flex justify-end gap-2">
                    <Button
                        href="/vendors/advancing"
                        variant="ghost"
                        :disabled="form.processing"
                    >
                        {{ $t('setup.actions.cancel') }}
                    </Button>
                    <Button
                        type="submit"
                        :loading="form.processing"
                    >
                        {{ $t('vendors.save') }}
                    </Button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
