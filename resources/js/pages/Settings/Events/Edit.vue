<script setup>
import EventEditShell from '../../../components/settings/EventEditShell.vue';
import { Button } from '../../../components/ui/button';
import { Card } from '../../../components/ui/card';
import { Dialog } from '../../../components/ui/dialog';
import { FormField } from '../../../components/ui/form-field';
import { Icon } from '../../../components/ui/icon';
import { Input } from '../../../components/ui/input';
import { Select } from '../../../components/ui/select';
import { useEventLockActions } from '../../../composables/useEventLockActions';
import { useFlashToast } from '../../../composables/useFlashToast';
import { fieldError, toastFormErrors } from '../../../lib/fieldError';
import { router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { trans } from 'laravel-vue-i18n';

const props = defineProps({
    event: {
        type: Object,
        required: true,
    },
    timezones: {
        type: Array,
        required: true,
    },
    forPrimary: {
        type: Boolean,
        default: false,
    },
    tab: {
        type: String,
        default: 'details',
    },
});

const { showError, showSuccess, showFormError } = useFlashToast();

const canWrite = computed(() => !props.event.is_read_only);
const deleteOpen = ref(false);
const deleteBusy = ref(false);

const {
    lockOpen,
    unlockOpen,
    actionBusy,
    openLock,
    openUnlock,
    confirmLock,
    confirmUnlock,
} = useEventLockActions();

const form = useForm({
    name: props.event.name ?? '',
    starts_on: props.event.starts_on ?? '',
    ends_on: props.event.ends_on ?? '',
    timezone: props.event.timezone ?? 'America/Vancouver',
});

const submit = () => {
    if (!canWrite.value) {
        return;
    }

    form.put(`/settings/events/${props.event.id}`, {
        onSuccess: () => showSuccess(trans('settings.events.toast.updated')),
        onError: (errors) =>
            toastFormErrors(form, errors, { showError, showFormError }),
    });
};

const confirmDelete = () => {
    if (deleteBusy.value) {
        return;
    }

    deleteBusy.value = true;
    router.delete(`/settings/events/${props.event.id}`, {
        onFinish: () => {
            deleteBusy.value = false;
        },
    });
};
</script>

<template>
    <EventEditShell
        :event="event"
        :tab="tab"
        :for-primary="forPrimary"
    >
        <Card class="p-6">
            <form @submit.prevent="submit">
                <FormField
                    :label="$t('settings.events.fields.name')"
                    :error="fieldError(form, 'name')"
                    required
                    class="mb-4"
                >
                    <template #default="{ id, invalid }">
                        <Input
                            :id="id"
                            v-model="form.name"
                            type="text"
                            :invalid="invalid"
                            :disabled="!canWrite"
                            autocomplete="off"
                        />
                    </template>
                </FormField>

                <div class="mb-4 grid grid-cols-1 gap-3.5">
                    <FormField
                        :label="$t('settings.events.fields.starts_on')"
                        :error="fieldError(form, 'starts_on')"
                        required
                    >
                        <template #default="{ id, invalid }">
                            <Input
                                :id="id"
                                v-model="form.starts_on"
                                type="date"
                                :invalid="invalid"
                                :disabled="!canWrite"
                            />
                        </template>
                    </FormField>
                    <FormField
                        :label="$t('settings.events.fields.ends_on')"
                        :error="fieldError(form, 'ends_on')"
                        required
                    >
                        <template #default="{ id, invalid }">
                            <Input
                                :id="id"
                                v-model="form.ends_on"
                                type="date"
                                :invalid="invalid"
                                :disabled="!canWrite"
                            />
                        </template>
                    </FormField>
                </div>

                <FormField
                    :label="$t('settings.events.fields.timezone')"
                    :error="fieldError(form, 'timezone')"
                    required
                    class="mb-6"
                >
                    <template #default="{ id, invalid }">
                        <Select
                            :id="id"
                            v-model="form.timezone"
                            :invalid="invalid"
                            :disabled="!canWrite"
                        >
                            <option
                                v-for="tz in timezones"
                                :key="tz"
                                :value="tz"
                            >
                                {{ tz }}
                            </option>
                        </Select>
                    </template>
                </FormField>

                <div
                    class="flex flex-col gap-2 sm:flex-row sm:justify-end sm:gap-3"
                >
                    <Button
                        v-if="canWrite"
                        type="submit"
                        variant="primary"
                        class="min-h-11 w-full sm:w-auto"
                        :loading="form.processing"
                        :disabled="form.processing"
                    >
                        {{ $t('settings.events.actions.save') }}
                    </Button>
                    <Button
                        type="button"
                        variant="outline"
                        class="min-h-11 w-full sm:w-auto"
                        href="/settings/events"
                    >
                        {{ $t('settings.events.actions.cancel') }}
                    </Button>
                </div>
            </form>
        </Card>

        <div
            class="mt-4 flex flex-col gap-2 sm:flex-row sm:justify-end sm:gap-3"
        >
            <Button
                type="button"
                variant="outline"
                class="min-h-11 w-full sm:w-auto"
                @click="event.is_locked ? openUnlock(event) : openLock(event)"
            >
                <Icon
                    :name="
                        event.is_locked ? ['fas', 'lock-open'] : ['fas', 'lock']
                    "
                    size="sm"
                />
                {{
                    event.is_locked
                        ? $t('events.actions.unlock')
                        : $t('events.actions.lock')
                }}
            </Button>
            <Button
                v-if="canWrite"
                type="button"
                variant="outline-danger"
                class="min-h-11 w-full sm:w-auto"
                @click="deleteOpen = true"
            >
                <Icon
                    :name="['fas', 'trash']"
                    size="sm"
                />
                {{ $t('settings.events.actions.delete') }}
            </Button>
        </div>

        <Dialog
            v-model:open="lockOpen"
            :title="$t('events.lock.title')"
            :description="$t('events.lock.body')"
            :confirm-label="$t('events.lock.confirm')"
            :cancel-label="$t('events.lock.cancel')"
            confirm-variant="secondary"
            :busy="actionBusy"
            @confirm="confirmLock"
        />

        <Dialog
            v-model:open="unlockOpen"
            :title="$t('events.unlock.title')"
            :description="$t('events.unlock.body')"
            :confirm-label="$t('events.unlock.confirm')"
            :cancel-label="$t('events.unlock.cancel')"
            confirm-variant="secondary"
            :busy="actionBusy"
            @confirm="confirmUnlock"
        />

        <Dialog
            v-model:open="deleteOpen"
            :title="$t('settings.events.delete.title')"
            :description="$t('settings.events.delete.body')"
            :confirm-label="$t('settings.events.delete.confirm')"
            :cancel-label="$t('settings.events.delete.cancel')"
            confirm-variant="danger"
            :busy="deleteBusy"
            @confirm="confirmDelete"
        />
    </EventEditShell>
</template>
