<script setup>
import { computed, ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { Dialog } from '../ui/dialog';
import { CustomDropdown } from '../ui/custom-dropdown';
import { FormField } from '../ui/form-field';
import { Input } from '../ui/input';
import ShiftRoleSlots from './ShiftRoleSlots.vue';
import { useFlashToast } from '../../composables/useFlashToast';
import { fieldError, toastFormErrors } from '../../lib/fieldError';
import { shiftSlotPayload, shiftSlotErrors } from '../../lib/shiftRoleSlots';

const props = defineProps({
    event: { type: Object, required: true },
    locations: { type: Array, required: true },
    roles: { type: Array, required: true },
});
const emit = defineEmits(['close']);
const form = useForm({
    name: '',
    location_id: props.locations[0]?.id ?? '',
    starts_at: '',
    ends_at: '',
    slots: [],
});
const slotErrors = ref({});
const { showError, showFormError } = useFlashToast();
const locationItems = computed(() =>
    props.locations.map((location) => ({
        value: location.id,
        title: location.name,
    })),
);
const submit = () => {
    if (form.processing) return;
    const submitted = [...form.slots];
    form.transform((data) => ({
        ...data,
        slots: shiftSlotPayload(data.slots),
    })).post('/team/events/' + props.event.id + '/shifts', {
        onError: (errors) => {
            slotErrors.value = shiftSlotErrors(submitted, errors);
            if (Object.keys(errors).some((key) => key.startsWith('slots'))) {
                showFormError(errors);
            } else {
                toastFormErrors(form, errors, { showError, showFormError });
            }
        },
        onSuccess: () => emit('close'),
    });
};
const clearSlotError = (key, field) => {
    if (slotErrors.value[key]) delete slotErrors.value[key][field];
};
</script>

<template>
    <Dialog
        :open="true"
        role="dialog"
        focus-trap
        sectioned
        class="max-w-xl"
        :title="$t('team.scheduling.actions.new')"
        :confirm-label="$t('team.scheduling.actions.create')"
        confirm-variant="primary"
        :confirm-disabled="locations.length === 0"
        :busy="form.processing"
        @cancel="emit('close')"
        @confirm="submit"
    >
        <form
            class="space-y-5"
            novalidate
            @submit.prevent="submit"
        >
            <FormField
                :label="$t('team.scheduling.fields.name')"
                :error="fieldError(form, 'name')"
            >
                <template #default="{ id, invalid }">
                    <Input
                        :id="id"
                        v-model="form.name"
                        :invalid="invalid"
                        :placeholder="
                            $t('team.scheduling.fields.name_placeholder')
                        "
                        :disabled="form.processing"
                    />
                </template>
            </FormField>

            <FormField
                :label="$t('team.scheduling.fields.location')"
                :error="fieldError(form, 'location_id')"
                required
            >
                <template #default="{ id, invalid }">
                    <CustomDropdown
                        :id="id"
                        v-model="form.location_id"
                        :items="locationItems"
                        :invalid="invalid"
                        :disabled="form.processing"
                        :placeholder="
                            $t('team.scheduling.fields.location_placeholder')
                        "
                        :empty-text="$t('team.scheduling.no_locations')"
                    />
                </template>
            </FormField>

            <div class="grid gap-4 sm:grid-cols-2">
                <FormField
                    :label="$t('team.scheduling.fields.start')"
                    :error="fieldError(form, 'starts_at')"
                    required
                >
                    <template #default="{ id, invalid }">
                        <Input
                            :id="id"
                            v-model="form.starts_at"
                            type="datetime-local"
                            :max="form.ends_at || undefined"
                            :invalid="invalid"
                            :disabled="form.processing"
                        />
                    </template>
                </FormField>
                <FormField
                    :label="$t('team.scheduling.fields.end')"
                    :error="fieldError(form, 'ends_at')"
                    required
                >
                    <template #default="{ id, invalid }">
                        <Input
                            :id="id"
                            v-model="form.ends_at"
                            type="datetime-local"
                            :min="form.starts_at || undefined"
                            :invalid="invalid"
                            :disabled="form.processing"
                        />
                    </template>
                </FormField>
            </div>

            <ShiftRoleSlots
                v-model="form.slots"
                :roles="roles"
                :errors="slotErrors"
                :busy="form.processing"
                :title="$t('team.scheduling.slots.create_title')"
                @clear-error="clearSlotError"
            />
            <p
                v-if="form.errors.slots"
                class="text-sm text-danger"
                role="alert"
            >
                {{ form.errors.slots }}
            </p>
        </form>
    </Dialog>
</template>
