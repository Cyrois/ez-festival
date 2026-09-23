<script setup>
import { useForm } from '@inertiajs/vue3';
import { watch } from 'vue';
import { Dialog } from '../ui/dialog';
import { FormField } from '../ui/form-field';
import { Input } from '../ui/input';
import { Select } from '../ui/select';
import { useFlashToast } from '../../composables/useFlashToast';

const props = defineProps({
    open: { type: Boolean, default: false },
    entitlement: { type: Object, default: null },
    endpoint: { type: String, default: '' },
});

const emit = defineEmits(['update:open']);
const form = useForm({ location_id: '', code: '' });
const { showFormError } = useFlashToast();

watch(
    () => [props.open, props.entitlement?.id],
    ([open]) => {
        if (!open) return;
        form.clearErrors();
        form.location_id = props.entitlement?.locations?.[0]?.id ?? '';
        form.code = '';
    },
);

const submit = () => {
    form.post(props.endpoint, {
        preserveScroll: true,
        onSuccess: () => emit('update:open', false),
        onError: showFormError,
    });
};
</script>

<template>
    <Dialog
        :open="open"
        :title="$t('artists.check_in.consume_title')"
        :description="entitlement?.name"
        :confirm-label="$t('artists.check_in.consume')"
        :busy="form.processing"
        confirm-variant="primary"
        @update:open="$emit('update:open', $event)"
        @confirm="submit"
    >
        <div class="mt-4 space-y-4">
            <FormField
                :label="$t('artists.check_in.location')"
                :error="form.errors.location_id"
                required
            >
                <template #default="{ id, invalid }">
                    <Select
                        :id="id"
                        v-model="form.location_id"
                        :invalid="invalid"
                    >
                        <option value="">
                            {{ $t('artists.check_in.choose_location') }}
                        </option>
                        <option
                            v-for="location in entitlement?.locations ?? []"
                            :key="location.id"
                            :value="location.id"
                        >
                            {{ location.name }} — {{ location.in_stock }}
                            {{ $t('artists.check_in.in_stock') }}
                        </option>
                    </Select>
                </template>
            </FormField>
            <FormField
                :label="$t('artists.check_in.code_optional')"
                :error="form.errors.code"
            >
                <template #default="{ id, invalid }">
                    <Input
                        :id="id"
                        v-model="form.code"
                        :invalid="invalid"
                    />
                </template>
            </FormField>
        </div>
    </Dialog>
</template>
