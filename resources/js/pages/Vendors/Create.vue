<script setup>
import AppLayout from '../../layouts/AppLayout.vue';
import { Button } from '../../components/ui/button';
import { Card } from '../../components/ui/card';
import { FormField } from '../../components/ui/form-field';
import { Input } from '../../components/ui/input';
import { Select } from '../../components/ui/select';
import { useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import { trans } from 'laravel-vue-i18n';

const props = defineProps({
    event: { type: Object, required: true },
    types: { type: Array, required: true },
});

const statuses = [
    'idea',
    'outreach',
    'negotiating',
    'contract_sent',
    'confirmed',
    'declined',
];
const form = useForm({
    name: '',
    status: 'idea',
    vendor_type_id: '',
});
const breadcrumbs = computed(() => [
    { label: trans('nav.vendors'), href: '/vendors/advancing' },
    { label: trans('vendors.add') },
]);

const submit = () => form.post(`/events/${props.event.id}/vendors`);
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
            <Card>
                <form
                    class="space-y-5"
                    @submit.prevent="submit"
                >
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
                    <div
                        class="flex justify-end gap-2 border-t border-line pt-5"
                    >
                        <Button
                            href="/vendors/advancing"
                            variant="ghost"
                            :disabled="form.processing"
                            >{{ $t('setup.actions.cancel') }}</Button
                        >
                        <Button
                            type="submit"
                            :loading="form.processing"
                            >{{ $t('vendors.save') }}</Button
                        >
                    </div>
                </form>
            </Card>
        </div>
    </AppLayout>
</template>
