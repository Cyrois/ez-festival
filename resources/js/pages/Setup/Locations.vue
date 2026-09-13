<script setup>
import SetupLayout from '../../layouts/SetupLayout.vue';
import { Button } from '../../components/ui/button';
import { Card } from '../../components/ui/card';
import { Dialog } from '../../components/ui/dialog';
import { FormField } from '../../components/ui/form-field';
import { Icon } from '../../components/ui/icon';
import { Input } from '../../components/ui/input';
import { useFlashToast } from '../../composables/useFlashToast';
import { fieldError, toastFormErrors } from '../../lib/fieldError';
import { useForm, router } from '@inertiajs/vue3';
import { trans } from 'laravel-vue-i18n';
import { computed, ref } from 'vue';

const props = defineProps({
    organization: { type: Object, required: true },
    event: { type: Object, required: true },
    locations: { type: Array, required: true },
    currentStep: { type: Number, required: true },
});

const { showFormError, showSuccess, showError } = useFlashToast();

const showAdd = ref(false);
const editingId = ref(null);
const editingSuggestionKey = ref(null);
const deleting = ref(null);
const deleteBusy = ref(false);
const continueBusy = ref(false);

let suggestionSeq = 0;
const makeSuggestions = () => [
    {
        key: `s-${++suggestionSeq}`,
        name: trans('setup.locations.defaults.main_stage'),
        type: '',
    },
    {
        key: `s-${++suggestionSeq}`,
        name: trans('setup.locations.defaults.headquarters'),
        type: '',
    },
];

const suggestions = ref(props.locations.length === 0 ? makeSuggestions() : []);

const addForm = useForm({ name: '', type: '' });
const editForm = useForm({ name: '', type: '' });
const suggestionForm = useForm({ name: '', type: '' });

const hasCards = computed(
    () => props.locations.length > 0 || suggestions.value.length > 0,
);

const submitAdd = () => {
    addForm.post('/setup/locations', {
        preserveScroll: true,
        onSuccess: () => {
            addForm.reset();
            showAdd.value = false;
            suggestions.value = [];
            showSuccess(trans('setup.toast.location_added'));
        },
        onError: (errors) =>
            toastFormErrors(addForm, errors, { showError, showFormError }),
    });
};

const startEdit = (location) => {
    editingSuggestionKey.value = null;
    editingId.value = location.id;
    editForm.name = location.name;
    editForm.type = location.type ?? '';
    editForm.clearErrors();
};

const startEditSuggestion = (item) => {
    editingId.value = null;
    editingSuggestionKey.value = item.key;
    suggestionForm.name = item.name;
    suggestionForm.type = item.type ?? '';
    suggestionForm.clearErrors();
};

const submitEdit = (location) => {
    editForm.put(`/setup/locations/${location.id}`, {
        preserveScroll: true,
        onSuccess: () => {
            editingId.value = null;
            showSuccess(trans('setup.toast.location_updated'));
        },
        onError: (errors) =>
            toastFormErrors(editForm, errors, { showError, showFormError }),
    });
};

const submitSuggestionEdit = (item) => {
    const name = suggestionForm.name.trim();
    if (!name) {
        suggestionForm.setError('name', trans('setup.errors.required'));
        return;
    }
    item.name = name;
    item.type = suggestionForm.type.trim();
    editingSuggestionKey.value = null;
};

const askDelete = (target) => {
    deleting.value = target;
};

const confirmDelete = () => {
    const target = deleting.value;
    if (!target) {
        return;
    }

    if (target.kind === 'suggestion') {
        suggestions.value = suggestions.value.filter(
            (item) => item.key !== target.key,
        );
        if (editingSuggestionKey.value === target.key) {
            editingSuggestionKey.value = null;
        }
        deleting.value = null;
        return;
    }

    deleteBusy.value = true;
    router.delete(`/setup/locations/${target.id}`, {
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

const continueSetup = () => {
    if (continueBusy.value) {
        return;
    }
    continueBusy.value = true;
    router.post(
        '/setup/locations/continue',
        {
            suggestions: suggestions.value.map((item) => ({
                name: item.name,
                type: item.type || null,
            })),
        },
        {
            onError: (errors) => showFormError(errors),
            onFinish: () => {
                continueBusy.value = false;
            },
        },
    );
};

const skip = () =>
    router.post(
        '/setup/locations/skip',
        {},
        {
            onError: (errors) => showFormError(errors),
        },
    );

const deleteTitle = computed(() => trans('setup.locations.delete_title'));
const deleteBody = computed(() => {
    if (!deleting.value) {
        return '';
    }
    if (deleting.value.kind === 'suggestion') {
        return trans('setup.locations.delete_suggested_body', {
            name: deleting.value.name,
        });
    }
    return trans('setup.locations.delete_body', { name: deleting.value.name });
});
</script>

<template>
    <SetupLayout
        :title="$t('setup.locations.title')"
        :current-step="currentStep"
        :organization-name="organization.name"
    >
        <div class="mb-5 flex items-start justify-between gap-3">
            <div>
                <h1 class="m-0 mb-1.5 text-[28px] font-bold tracking-tight">
                    {{ $t('setup.locations.heading') }}
                </h1>
                <p class="m-0 text-sm leading-snug text-muted">
                    {{ $t('setup.locations.lead') }}
                </p>
            </div>
            <Button
                type="button"
                variant="primary"
                size="sm"
                class="shrink-0"
                @click="showAdd = !showAdd"
            >
                {{ $t('setup.locations.add') }}
            </Button>
        </div>

        <Card
            v-if="showAdd"
            class="mb-4"
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
            v-if="hasCards"
            class="mb-4 flex flex-col gap-3"
        >
            <Card
                v-for="location in locations"
                :key="`loc-${location.id}`"
            >
                <form
                    v-if="editingId === location.id"
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
                                :placeholder="
                                    $t('setup.locations.name_placeholder')
                                "
                                :invalid="invalid"
                            />
                        </template>
                    </FormField>
                    <FormField
                        :label="$t('setup.locations.type')"
                        :error="fieldError(editForm, 'type')"
                    >
                        <template #default="{ id, invalid }">
                            <Input
                                :id="id"
                                v-model="editForm.type"
                                type="text"
                                :placeholder="
                                    $t('setup.locations.type_placeholder')
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
                    <div class="flex shrink-0 items-center gap-1">
                        <Button
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
                            type="button"
                            variant="outline-danger"
                            size="icon"
                            :aria-label="$t('setup.actions.delete')"
                            @click="
                                askDelete({
                                    kind: 'saved',
                                    id: location.id,
                                    name: location.name,
                                })
                            "
                        >
                            <Icon
                                :name="['fas', 'trash-can']"
                                size="sm"
                            />
                        </Button>
                    </div>
                </div>
            </Card>

            <Card
                v-for="item in suggestions"
                :key="item.key"
            >
                <form
                    v-if="editingSuggestionKey === item.key"
                    class="flex w-full flex-col gap-3"
                    @submit.prevent="submitSuggestionEdit(item)"
                >
                    <FormField
                        :label="$t('setup.locations.name')"
                        :error="fieldError(suggestionForm, 'name')"
                        required
                    >
                        <template #default="{ id, invalid }">
                            <Input
                                :id="id"
                                v-model="suggestionForm.name"
                                type="text"
                                :placeholder="
                                    $t('setup.locations.name_placeholder')
                                "
                                :invalid="invalid"
                            />
                        </template>
                    </FormField>
                    <FormField
                        :label="$t('setup.locations.type')"
                        :error="fieldError(suggestionForm, 'type')"
                    >
                        <template #default="{ id, invalid }">
                            <Input
                                :id="id"
                                v-model="suggestionForm.type"
                                type="text"
                                :placeholder="
                                    $t('setup.locations.type_placeholder')
                                "
                                :invalid="invalid"
                            />
                        </template>
                    </FormField>
                    <div class="flex justify-end gap-3">
                        <Button
                            type="button"
                            variant="outline"
                            @click="editingSuggestionKey = null"
                        >
                            {{ $t('setup.actions.cancel') }}
                        </Button>
                        <Button
                            type="submit"
                            variant="primary"
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
                        <strong class="block font-bold">{{ item.name }}</strong>
                        <div
                            v-if="item.type"
                            class="mt-0.5 text-xs text-muted"
                        >
                            {{ item.type }}
                        </div>
                        <div class="mt-1 text-xs font-medium text-secondary">
                            {{ $t('setup.locations.suggested_label') }}
                        </div>
                    </div>
                    <div class="flex shrink-0 items-center gap-1">
                        <Button
                            type="button"
                            variant="ghost"
                            size="icon"
                            :aria-label="$t('setup.actions.edit')"
                            @click="startEditSuggestion(item)"
                        >
                            <Icon
                                :name="['fas', 'pencil']"
                                size="sm"
                            />
                        </Button>
                        <Button
                            type="button"
                            variant="outline-danger"
                            size="icon"
                            :aria-label="$t('setup.actions.delete')"
                            @click="
                                askDelete({
                                    kind: 'suggestion',
                                    key: item.key,
                                    name: item.name,
                                })
                            "
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
            class="mb-4 rounded-xl border border-dashed border-line px-4 py-8 text-center text-sm text-muted"
        >
            {{ $t('setup.locations.empty') }}
        </div>

        <p class="mb-4 text-xs leading-snug text-muted">
            {{ $t('setup.locations.note') }}
        </p>

        <div class="mt-5 flex items-center justify-between gap-4">
            <div class="flex items-center gap-4">
                <Button
                    variant="secondary"
                    href="/setup/event"
                >
                    {{ $t('setup.actions.back') }}
                </Button>
                <Button
                    type="button"
                    variant="secondary"
                    @click="skip"
                >
                    {{ $t('setup.actions.skip') }}
                </Button>
            </div>
            <Button
                type="button"
                variant="primary"
                :loading="continueBusy"
                :disabled="continueBusy"
                @click="continueSetup"
            >
                {{ $t('setup.actions.save_continue') }}
            </Button>
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
    </SetupLayout>
</template>
