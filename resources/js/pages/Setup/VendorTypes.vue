<script setup>
import SetupLayout from '../../layouts/SetupLayout.vue';
import { Button } from '../../components/ui/button';
import { FormField } from '../../components/ui/form-field';
import { Input } from '../../components/ui/input';
import { useFlashToast } from '../../composables/useFlashToast';
import { useForm, router } from '@inertiajs/vue3';
import { trans } from 'laravel-vue-i18n';
import { ref } from 'vue';

const props = defineProps({
    organization: { type: Object, required: true },
    event: { type: Object, default: null },
    types: { type: Array, required: true },
    currentStep: { type: Number, required: true },
});

const { showFormError, showSuccess, showError } = useFlashToast();

const showAdd = ref(false);
const editingId = ref(null);

const addForm = useForm({ name: '' });
const editForm = useForm({ name: '' });

const fieldError = (form, key) => {
    const error = form.errors[key];
    if (!error) {
        return '';
    }

    const value = form[key];
    if (value === '' || value === null || value === undefined) {
        return trans('setup.errors.required');
    }

    return error;
};

const submitAdd = () => {
    addForm.post('/setup/vendor-types', {
        preserveScroll: true,
        onSuccess: () => {
            addForm.reset();
            showAdd.value = false;
            showSuccess(trans('setup.toast.vendor_type_added'));
        },
        onError: () => showError(trans('setup.errors.required_fields')),
    });
};

const startEdit = (type) => {
    editingId.value = type.id;
    editForm.name = type.name;
    editForm.clearErrors();
};

const submitEdit = (type) => {
    editForm.put(`/setup/vendor-types/${type.id}`, {
        preserveScroll: true,
        onSuccess: () => {
            editingId.value = null;
            showSuccess(trans('setup.toast.vendor_type_updated'));
        },
        onError: () => showError(trans('setup.errors.required_fields')),
    });
};

const continueSetup = () =>
    router.post(
        '/setup/vendor-types/continue',
        {},
        {
            onError: (errors) => showFormError(errors),
        },
    );
const skip = () =>
    router.post(
        '/setup/vendor-types/skip',
        {},
        {
            onError: (errors) => showFormError(errors),
        },
    );
</script>

<template>
    <SetupLayout
        :title="$t('setup.vendor_types.title')"
        :current-step="currentStep"
        :organization-name="organization.name"
    >
        <div class="mb-5 flex items-start justify-between gap-3">
            <div>
                <h1 class="m-0 mb-1.5 text-[28px] font-bold tracking-tight">
                    {{ $t('setup.vendor_types.heading') }}
                </h1>
                <p class="m-0 text-sm leading-snug text-muted">
                    {{ $t('setup.vendor_types.lead') }}
                </p>
            </div>
            <Button
                type="button"
                variant="primary"
                size="sm"
                class="shrink-0"
                @click="showAdd = !showAdd"
            >
                {{ $t('setup.vendor_types.add') }}
            </Button>
        </div>

        <form
            v-if="showAdd"
            class="mb-4 rounded-xl border border-line bg-ground px-6 py-6"
            @submit.prevent="submitAdd"
        >
            <FormField
                :label="$t('setup.types.name')"
                :error="fieldError(addForm, 'name')"
                required
                class="mb-4"
            >
                <template #default="{ id, invalid }">
                    <Input
                        :id="id"
                        v-model="addForm.name"
                        type="text"
                        :placeholder="$t('setup.types.name_placeholder')"
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

        <div
            v-if="types.length"
            class="mb-4 overflow-hidden rounded-xl border border-line bg-ground"
        >
            <div
                v-for="type in types"
                :key="type.id"
                class="flex items-center justify-between border-b border-line px-4 py-3 text-sm last:border-b-0"
            >
                <template v-if="editingId === type.id">
                    <form
                        class="flex w-full flex-col gap-3 sm:flex-row sm:items-end"
                        @submit.prevent="submitEdit(type)"
                    >
                        <FormField
                            :label="$t('setup.types.name')"
                            :error="fieldError(editForm, 'name')"
                            required
                            class="min-w-0 flex-1"
                        >
                            <template #default="{ id, invalid }">
                                <Input
                                    :id="id"
                                    v-model="editForm.name"
                                    type="text"
                                    :placeholder="
                                        $t('setup.types.name_placeholder')
                                    "
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
                </template>
                <template v-else>
                    <strong class="font-bold">{{ type.name }}</strong>
                    <Button
                        type="button"
                        variant="ghost"
                        size="sm"
                        class="text-muted"
                        @click="startEdit(type)"
                    >
                        {{ $t('setup.actions.edit') }}
                    </Button>
                </template>
            </div>
        </div>
        <div
            v-else
            class="mb-4 rounded-xl border border-dashed border-line px-4 py-8 text-center text-sm text-muted"
        >
            {{ $t('setup.vendor_types.empty') }}
        </div>

        <p class="mb-4 text-xs leading-snug text-muted">
            {{ $t('setup.vendor_types.note') }}
        </p>

        <div class="mt-5 flex items-center justify-end gap-4">
            <Button
                variant="ghost"
                href="/setup/locations"
                class="text-secondary hover:bg-secondary-soft hover:text-secondary"
            >
                {{ $t('setup.actions.back') }}
            </Button>
            <Button
                type="button"
                variant="ghost"
                class="text-secondary hover:bg-secondary-soft hover:text-secondary"
                @click="skip"
            >
                {{ $t('setup.actions.skip') }}
            </Button>
            <Button
                type="button"
                variant="primary"
                @click="continueSetup"
            >
                {{ $t('setup.actions.save_continue') }}
            </Button>
        </div>
    </SetupLayout>
</template>
