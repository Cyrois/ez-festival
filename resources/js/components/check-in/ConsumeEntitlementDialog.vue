<script setup>
import { useForm } from '@inertiajs/vue3';
import { computed, watch } from 'vue';
import { trans } from 'laravel-vue-i18n';
import { Dialog } from '../ui/dialog';
import { FormField } from '../ui/form-field';
import { Input } from '../ui/input';
import { CustomDropdown } from '../ui/custom-dropdown';
import { useFlashToast } from '../../composables/useFlashToast';

const props = defineProps({
    open: { type: Boolean, default: false },
    entitlement: { type: Object, default: null },
    endpoint: { type: String, default: '' },
});

const emit = defineEmits(['update:open']);
const form = useForm({ location_id: '', code: '' });
const { showFormError } = useFlashToast();
const locationItems = computed(() => [
    { value: '', title: trans('artists.check_in.choose_location') },
    ...(props.entitlement?.locations ?? []).map((location) => ({
        value: location.id,
        title: trans('artists.check_in.location_stock', {
            location: location.name,
            count: location.in_stock,
        }),
    })),
]);

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
                    <CustomDropdown
                        :id="id"
                        v-model="form.location_id"
                        :items="locationItems"
                        :invalid="invalid"
                        :disabled="form.processing"
                    />
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
