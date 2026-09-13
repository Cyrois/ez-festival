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
    event: { type: Object, default: null },
    types: { type: Array, required: true },
    currentStep: { type: Number, required: true },
});

const { showFormError, showSuccess, showError } = useFlashToast();

const showAdd = ref(false);
const editingId = ref(null);
const editingSuggestionKey = ref(null);
const deleting = ref(null);
const deleteBusy = ref(false);
const finishBusy = ref(false);

let suggestionSeq = 0;
const makeSuggestions = () => [
    {
        key: `s-${++suggestionSeq}`,
        name: trans('setup.artist_types.defaults.performance'),
    },
    {
        key: `s-${++suggestionSeq}`,
        name: trans('setup.artist_types.defaults.painter'),
    },
];

const suggestions = ref(props.types.length === 0 ? makeSuggestions() : []);

const addForm = useForm({ name: '' });
const editForm = useForm({ name: '' });
const suggestionForm = useForm({ name: '' });

const hasCards = computed(
    () => props.types.length > 0 || suggestions.value.length > 0,
);

const submitAdd = () => {
    addForm.post('/setup/artist-types', {
        preserveScroll: true,
        onSuccess: () => {
            addForm.reset();
            showAdd.value = false;
            suggestions.value = [];
            showSuccess(trans('setup.toast.artist_type_added'));
        },
        onError: (errors) =>
            toastFormErrors(addForm, errors, { showError, showFormError }),
    });
};

const startEdit = (type) => {
    editingSuggestionKey.value = null;
    editingId.value = type.id;
    editForm.name = type.name;
    editForm.clearErrors();
};

const startEditSuggestion = (item) => {
    editingId.value = null;
    editingSuggestionKey.value = item.key;
    suggestionForm.name = item.name;
    suggestionForm.clearErrors();
};

const submitEdit = (type) => {
    editForm.put(`/setup/artist-types/${type.id}`, {
        preserveScroll: true,
        onSuccess: () => {
            editingId.value = null;
            showSuccess(trans('setup.toast.artist_type_updated'));
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
    router.delete(`/setup/artist-types/${target.id}`, {
        preserveScroll: true,
        onSuccess: () => {
            showSuccess(trans('setup.toast.artist_type_deleted'));
            deleting.value = null;
        },
        onError: (errors) => showFormError(errors),
        onFinish: () => {
            deleteBusy.value = false;
        },
    });
};

const finish = () => {
    if (finishBusy.value) {
        return;
    }
    finishBusy.value = true;
    router.post(
        '/setup/artist-types/continue',
        {
            suggestions: suggestions.value.map((item) => ({
                name: item.name,
            })),
        },
        {
            onError: (errors) => showFormError(errors),
            onFinish: () => {
                finishBusy.value = false;
            },
        },
    );
};

const skip = () =>
    router.post(
        '/setup/artist-types/skip',
        {},
        {
            onError: (errors) => showFormError(errors),
        },
    );

const deleteTitle = computed(() => trans('setup.artist_types.delete_title'));
const deleteBody = computed(() => {
    if (!deleting.value) {
        return '';
    }
    if (deleting.value.kind === 'suggestion') {
        return trans('setup.artist_types.delete_suggested_body', {
            name: deleting.value.name,
        });
    }
    return trans('setup.artist_types.delete_body', {
        name: deleting.value.name,
    });
});
</script>

<template>
    <SetupLayout
        :title="$t('setup.artist_types.title')"
        :current-step="currentStep"
        :organization-name="organization.name"
    >
        <div class="mb-5 flex items-start justify-between gap-3">
            <div>
                <h1 class="m-0 mb-1.5 text-[28px] font-bold tracking-tight">
                    {{ $t('setup.artist_types.heading') }}
                </h1>
                <p class="m-0 text-sm leading-snug text-muted">
                    {{ $t('setup.artist_types.lead') }}
                </p>
            </div>
            <Button
                type="button"
                variant="primary"
                size="sm"
                class="shrink-0"
                @click="showAdd = !showAdd"
            >
                {{ $t('setup.artist_types.add') }}
            </Button>
        </div>

        <Card
            v-if="showAdd"
            class="mb-4"
        >
            <form @submit.prevent="submitAdd">
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
                            :placeholder="
                                $t('setup.artist_types.name_placeholder')
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
                v-for="type in types"
                :key="`type-${type.id}`"
            >
                <form
                    v-if="editingId === type.id"
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
                                    $t('setup.artist_types.name_placeholder')
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
                    class="flex items-center justify-between gap-3"
                >
                    <strong class="min-w-0 font-bold">{{ type.name }}</strong>
                    <div class="flex shrink-0 items-center gap-1">
                        <Button
                            type="button"
                            variant="ghost"
                            size="icon"
                            :aria-label="$t('setup.actions.edit')"
                            @click="startEdit(type)"
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
                                    id: type.id,
                                    name: type.name,
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
                    class="flex w-full flex-col gap-3 sm:flex-row sm:items-end"
                    @submit.prevent="submitSuggestionEdit(item)"
                >
                    <FormField
                        :label="$t('setup.types.name')"
                        :error="fieldError(suggestionForm, 'name')"
                        required
                        class="min-w-0 flex-1"
                    >
                        <template #default="{ id, invalid }">
                            <Input
                                :id="id"
                                v-model="suggestionForm.name"
                                type="text"
                                :placeholder="
                                    $t('setup.artist_types.name_placeholder')
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
                    class="flex items-center justify-between gap-3"
                >
                    <div class="min-w-0">
                        <strong class="block font-bold">{{ item.name }}</strong>
                        <div class="mt-1 text-xs font-medium text-secondary">
                            {{ $t('setup.artist_types.suggested_label') }}
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
            {{ $t('setup.artist_types.empty') }}
        </div>

        <p class="mb-4 text-xs leading-snug text-muted">
            {{ $t('setup.artist_types.note') }}
        </p>

        <div class="mt-5 flex items-center justify-between gap-4">
            <div class="flex items-center gap-4">
                <Button
                    variant="secondary"
                    href="/setup/vendor-types"
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
                :loading="finishBusy"
                :disabled="finishBusy"
                @click="finish"
            >
                {{ $t('setup.actions.finish') }}
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
