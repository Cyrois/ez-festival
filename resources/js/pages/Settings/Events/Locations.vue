<script setup>
import EventEditShell from '../../../components/settings/EventEditShell.vue';
import { Button } from '../../../components/ui/button';
import { Card } from '../../../components/ui/card';
import { Dialog } from '../../../components/ui/dialog';
import { FormField } from '../../../components/ui/form-field';
import { Icon } from '../../../components/ui/icon';
import { Input } from '../../../components/ui/input';
import { useFlashToast } from '../../../composables/useFlashToast';
import { fieldError, toastFormErrors } from '../../../lib/fieldError';
import { useForm, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { trans } from 'laravel-vue-i18n';

const props = defineProps({
    event: {
        type: Object,
        required: true,
    },
    locations: {
        type: Array,
        default: () => [],
    },
    forPrimary: {
        type: Boolean,
        default: false,
    },
    tab: {
        type: String,
        default: 'locations',
    },
    canAddLocations: { type: Boolean, default: false },
    canRenameLocations: { type: Boolean, default: false },
    canDeleteLocations: { type: Boolean, default: false },
    canEditLocationTypes: { type: Boolean, default: false },
});

const { showFormError, showSuccess, showError } = useFlashToast();

const showAdd = ref(false);
const editingId = ref(null);
const deleting = ref(null);
const deleteBusy = ref(false);

const addForm = useForm({ name: '', type: '' });
const editForm = useForm({ name: '', type: '' });
const locationPayload = (data) =>
    props.canEditLocationTypes ? data : { name: data.name };

const submitAdd = () => {
    if (!props.canAddLocations) {
        return;
    }

    addForm
        .transform(locationPayload)
        .post(`/settings/events/${props.event.id}/locations`, {
            preserveScroll: true,
            onSuccess: () => {
                addForm.reset();
                showAdd.value = false;
                showSuccess(trans('setup.toast.location_added'));
            },
            onError: (errors) =>
                toastFormErrors(addForm, errors, { showError, showFormError }),
        });
};

const startEdit = (location) => {
    if (!props.canRenameLocations) return;
    editingId.value = location.id;
    editForm.name = location.name;
    editForm.type = location.type ?? '';
    editForm.clearErrors();
};

const submitEdit = (location) => {
    if (!props.canRenameLocations) {
        return;
    }

    editForm
        .transform(locationPayload)
        .put(`/settings/events/${props.event.id}/locations/${location.id}`, {
            preserveScroll: true,
            onSuccess: () => {
                editingId.value = null;
                showSuccess(trans('setup.toast.location_updated'));
            },
            onError: (errors) =>
                toastFormErrors(editForm, errors, {
                    showError,
                    showFormError,
                }),
        });
};

const askDelete = (location) => {
    if (!props.canDeleteLocations) return;
    deleting.value = location;
};

const confirmDelete = () => {
    const target = deleting.value;
    if (!target || !props.canDeleteLocations) {
        return;
    }

    deleteBusy.value = true;
    router.delete(`/settings/events/${props.event.id}/locations/${target.id}`, {
        preserveScroll: true,
        onSuccess: () => {
            showSuccess(trans('setup.toast.location_deleted'));
            deleting.value = null;
        },
        onError: (errors) => showFormError(errors),
        onFinish: () => {
            deleteBusy.value = false;
        },
    });
};

const deleteTitle = computed(() => trans('setup.locations.delete_title'));
const deleteBody = computed(() => {
    if (!deleting.value) {
        return '';
    }
    return trans('setup.locations.delete_body', {
        name: deleting.value.name,
    });
});
</script>

<template>
    <EventEditShell
        :event="event"
        :tab="tab"
        :for-primary="forPrimary"
    >
        <div
            v-if="canAddLocations"
            class="mb-4 flex justify-end"
        >
            <Button
                type="button"
                variant="primary"
                @click="showAdd = !showAdd"
            >
                <Icon
                    :name="['fas', 'plus']"
                    size="sm"
                    class="mr-1.5"
                />
                {{ $t('setup.locations.add') }}
            </Button>
        </div>

        <Card
            v-if="showAdd && canAddLocations"
            class="mb-4 p-4"
        >
            <form @submit.prevent="submitAdd">
                <FormField
                    :label="$t('setup.locations.name')"
                    :error="fieldError(addForm, 'name')"
                    required
                    class="mb-4"
                >
                    <template #default="{ id, invalid }">
                        <Input
                            :id="id"
                            v-model="addForm.name"
                            type="text"
                            :placeholder="
                                $t('setup.locations.name_placeholder')
                            "
                            :invalid="invalid"
                            autocomplete="off"
                        />
                    </template>
                </FormField>
                <FormField
                    v-if="canEditLocationTypes"
                    :label="$t('setup.locations.type')"
                    :error="fieldError(addForm, 'type')"
                    class="mb-4"
                >
                    <template #default="{ id, invalid }">
                        <Input
                            :id="id"
                            v-model="addForm.type"
                            type="text"
                            :placeholder="
                                $t('setup.locations.type_placeholder')
                            "
                            :invalid="invalid"
                            autocomplete="off"
                        />
                    </template>
                </FormField>
                <div class="flex justify-end gap-3">
                    <Button
                        type="button"
                        variant="outline"
                        @click="showAdd = false"
                    >
                        {{ $t('setup.actions.cancel') }}
                    </Button>
                    <Button
                        type="submit"
                        variant="primary"
                        :loading="addForm.processing"
                        :disabled="addForm.processing"
                    >
                        {{ $t('setup.actions.add') }}
                    </Button>
                </div>
            </form>
        </Card>

        <div
            v-if="locations.length > 0"
            class="flex flex-col gap-3"
        >
            <Card
                v-for="location in locations"
                :key="location.id"
                class="p-4"
            >
                <form
                    v-if="editingId === location.id && canRenameLocations"
                    class="flex w-full flex-col gap-3"
                    @submit.prevent="submitEdit(location)"
                >
                    <FormField
                        :label="$t('setup.locations.name')"
                        :error="fieldError(editForm, 'name')"
                        required
                    >
                        <template #default="{ id, invalid }">
                            <Input
                                :id="id"
                                v-model="editForm.name"
                                type="text"
                                :invalid="invalid"
                            />
                        </template>
                    </FormField>
                    <FormField
                        v-if="canEditLocationTypes"
                        :label="$t('setup.locations.type')"
                        :error="fieldError(editForm, 'type')"
                    >
                        <template #default="{ id, invalid }">
                            <Input
                                :id="id"
                                v-model="editForm.type"
                                type="text"
                                :invalid="invalid"
                            />
                        </template>
                    </FormField>
                    <div class="flex justify-end gap-3">
                        <Button
                            type="button"
                            variant="outline"
                            @click="editingId = null"
                        >
                            {{ $t('setup.actions.cancel') }}
                        </Button>
                        <Button
                            type="submit"
                            variant="primary"
                            :loading="editForm.processing"
                            :disabled="editForm.processing"
                        >
                            {{ $t('setup.actions.save') }}
                        </Button>
                    </div>
                </form>
                <div
                    v-else
                    class="flex items-start justify-between gap-3"
                >
                    <div class="min-w-0">
                        <strong class="block font-bold">{{
                            location.name
                        }}</strong>
                        <div
                            v-if="location.type"
                            class="mt-0.5 text-xs text-muted"
                        >
                            {{ location.type }}
                        </div>
                    </div>
                    <div
                        v-if="canRenameLocations || canDeleteLocations"
                        class="flex shrink-0 items-center gap-1"
                    >
                        <Button
                            v-if="canRenameLocations"
                            type="button"
                            variant="ghost"
                            size="icon"
                            :aria-label="$t('setup.actions.edit')"
                            @click="startEdit(location)"
                        >
                            <Icon
                                :name="['fas', 'pencil']"
                                size="sm"
                            />
                        </Button>
                        <Button
                            v-if="canDeleteLocations"
                            type="button"
                            variant="outline-danger"
                            size="icon"
                            :aria-label="$t('setup.actions.delete')"
                            @click="askDelete(location)"
                        >
                            <Icon
                                :name="['fas', 'trash-can']"
                                size="sm"
                            />
                        </Button>
                    </div>
                </div>
            </Card>
        </div>
        <div
            v-else
            class="rounded-xl border border-dashed border-line px-4 py-8 text-center text-sm text-muted"
        >
            {{ $t('setup.locations.empty') }}
        </div>

        <Dialog
            :open="Boolean(deleting)"
            :title="deleteTitle"
            :description="deleteBody"
            :confirm-label="$t('setup.actions.delete')"
            :cancel-label="$t('setup.actions.cancel')"
            confirm-variant="danger"
            :busy="deleteBusy"
            @update:open="(open) => !open && (deleting = null)"
            @confirm="confirmDelete"
            @cancel="deleting = null"
        />
    </EventEditShell>
</template>
