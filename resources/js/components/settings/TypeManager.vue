<script setup>
import { Button } from '../ui/button';
import { Card } from '../ui/card';
import { Dialog } from '../ui/dialog';
import { FormField } from '../ui/form-field';
import { Icon } from '../ui/icon';
import { Input } from '../ui/input';
import { useFlashToast } from '../../composables/useFlashToast';
import { fieldError, toastFormErrors } from '../../lib/fieldError';
import { router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { trans } from 'laravel-vue-i18n';

const props = defineProps({
    types: { type: Array, default: () => [] },
    endpoint: { type: String, required: true },
    emptyKey: { type: String, required: true },
});

const { showFormError, showSuccess, showError } = useFlashToast();
const orderedTypes = ref([...props.types]);
const editingId = ref(null);
const draggedId = ref(null);
const activeDropIndex = ref(null);
const deleting = ref(null);
const deleteBusy = ref(false);
const addForm = useForm({ name: '' });
const editForm = useForm({ name: '' });
const newType = computed(() => orderedTypes.value.find((type) => type.isNew));

const addType = () => {
    if (newType.value) return;
    editingId.value = null;
    addForm.reset();
    addForm.clearErrors();
    orderedTypes.value.unshift({
        id: `new-${Date.now()}`,
        name: '',
        isNew: true,
    });
};
const cancelAdd = () => {
    orderedTypes.value = orderedTypes.value.filter((type) => !type.isNew);
    addForm.reset();
    addForm.clearErrors();
};
const submitAdd = () => {
    addForm
        .transform((data) => ({
            name: data.name,
            types: orderedTypes.value
                .filter((type) => !type.isNew)
                .map((type, position) => ({ id: type.id, position })),
        }))
        .post(props.endpoint, {
            preserveScroll: true,
            onSuccess: (page) => {
                addForm.reset();
                orderedTypes.value = [...(page.props.types ?? [])];
                showSuccess(trans('settings.type_actions.saved'));
            },
            onError: (errors) =>
                toastFormErrors(addForm, errors, { showError, showFormError }),
        });
};
const startEdit = (type) => {
    editingId.value = type.id;
    editForm.name = type.name;
    editForm.clearErrors();
};
const cancelEdit = () => {
    editingId.value = null;
    editForm.reset();
    editForm.clearErrors();
};
const submitEdit = (type) => {
    editForm.put(`${props.endpoint}/${type.id}`, {
        preserveScroll: true,
        onSuccess: () => {
            type.name = editForm.name;
            cancelEdit();
            showSuccess(trans('settings.type_actions.updated'));
        },
        onError: (errors) =>
            toastFormErrors(editForm, errors, { showError, showFormError }),
    });
};
const askDelete = (type) => {
    deleting.value = type;
};
const confirmDelete = () => {
    if (!deleting.value) return;
    deleteBusy.value = true;
    router.delete(`${props.endpoint}/${deleting.value.id}`, {
        preserveScroll: true,
        onSuccess: () => {
            orderedTypes.value = orderedTypes.value.filter(
                (type) => type.id !== deleting.value.id,
            );
            deleting.value = null;
            showSuccess(trans('settings.type_actions.deleted'));
        },
        onError: (errors) => showFormError(errors),
        onFinish: () => {
            deleteBusy.value = false;
        },
    });
};
const persistOrder = () => {
    router.post(
        `${props.endpoint}/reorder`,
        {
            types: orderedTypes.value.map((type, position) => ({
                id: type.id,
                position,
            })),
        },
        {
            preserveScroll: true,
            onError: () => {
                orderedTypes.value = [...props.types];
            },
        },
    );
};
const startDrag = (event, id) => {
    draggedId.value = id;
    activeDropIndex.value = null;
    event.dataTransfer.effectAllowed = 'move';
};
const endDrag = () => {
    draggedId.value = null;
    activeDropIndex.value = null;
};
const updateDropIndex = (event, index) => {
    if (draggedId.value === null) return;
    const bounds = event.currentTarget.getBoundingClientRect();
    activeDropIndex.value =
        event.clientY < bounds.top + bounds.height / 2 ? index : index + 1;
};
const dropType = () => {
    const targetIndex = activeDropIndex.value;
    const from = orderedTypes.value.findIndex(
        (type) => type.id === draggedId.value,
    );
    if (targetIndex === null || from === -1) {
        endDrag();
        return;
    }
    let to = targetIndex;
    if (from < to) to -= 1;
    if (from === to) {
        endDrag();
        return;
    }
    const [moved] = orderedTypes.value.splice(from, 1);
    orderedTypes.value.splice(to, 0, moved);
    endDrag();
    persistOrder();
};
const deleteBody = computed(() => {
    if (!deleting.value) return '';

    const key = deleting.value.affected_count
        ? 'settings.type_actions.delete_body_affected'
        : 'settings.type_actions.delete_body';

    return trans(key, {
        name: deleting.value.name,
        count: deleting.value.affected_count,
    });
});
</script>

<template>
    <div class="mb-6 flex items-start justify-between gap-3">
        <slot name="header" />
        <Button
            type="button"
            variant="primary"
            size="sm"
            :disabled="Boolean(newType)"
            @click="addType"
        >
            <Icon
                :name="['fas', 'plus']"
                size="sm"
                class="mr-1.5"
            />
            {{ $t('settings.type_actions.create') }}
        </Button>
    </div>
    <div
        v-if="orderedTypes.length === 0"
        class="rounded-lg border border-dashed border-line px-4 py-8 text-center text-sm text-muted"
    >
        {{ $t(emptyKey) }}
    </div>
    <div
        v-else
        class="flex flex-col gap-3"
    >
        <div
            v-if="draggedId !== null && activeDropIndex === 0"
            class="min-h-[72px] rounded-lg border-2 border-dashed border-primary bg-primary/10"
            @dragover.prevent
            @drop.prevent="dropType"
        />
        <Card
            v-for="(type, index) in orderedTypes"
            :key="type.id"
            class="p-4"
            :class="
                draggedId === type.id ? 'ring-2 ring-primary ring-offset-2' : ''
            "
            @dragover.prevent="!type.isNew && updateDropIndex($event, index)"
            @drop.prevent="!type.isNew && dropType()"
        >
            <form
                v-if="type.isNew"
                class="flex w-full flex-col gap-3 sm:flex-row sm:items-end"
                @submit.prevent="submitAdd"
            >
                <FormField
                    :label="$t('setup.types.name')"
                    :error="fieldError(addForm, 'name')"
                    required
                    class="min-w-0 flex-1"
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
                <div class="flex justify-end gap-2">
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        :aria-label="$t('settings.type_actions.cancel')"
                        @click="cancelAdd"
                    >
                        <Icon :name="['fas', 'xmark']" />
                    </Button>
                    <Button
                        type="submit"
                        variant="primary"
                        size="sm"
                        :aria-label="$t('settings.type_actions.save')"
                        :loading="addForm.processing"
                        :disabled="addForm.processing"
                    >
                        <Icon :name="['fas', 'check']" />
                    </Button>
                </div>
            </form>
            <form
                v-else-if="editingId === type.id"
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
                            :invalid="invalid"
                        />
                    </template>
                </FormField>
                <div class="flex justify-end gap-2">
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        :aria-label="$t('settings.type_actions.cancel')"
                        @click="cancelEdit"
                    >
                        <Icon :name="['fas', 'xmark']" />
                    </Button>
                    <Button
                        type="submit"
                        variant="primary"
                        size="sm"
                        :aria-label="$t('settings.type_actions.save')"
                        :loading="editForm.processing"
                        :disabled="editForm.processing"
                    >
                        <Icon :name="['fas', 'check']" />
                    </Button>
                </div>
            </form>
            <div
                v-else
                class="flex items-center gap-3"
            >
                <Button
                    type="button"
                    variant="ghost"
                    size="sm"
                    class="cursor-grab touch-none px-2 text-muted active:cursor-grabbing"
                    :aria-label="$t('settings.type_actions.drag_handle')"
                    draggable="true"
                    @dragstart="startDrag($event, type.id)"
                    @dragend="endDrag"
                >
                    <Icon
                        :name="['fas', 'grip-lines']"
                        fixed-width
                    />
                </Button>
                <strong class="min-w-0 flex-1 font-bold">{{
                    type.name
                }}</strong>
                <div class="flex shrink-0 gap-2">
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        :aria-label="$t('settings.type_actions.edit')"
                        @click="startEdit(type)"
                    >
                        <Icon :name="['fas', 'pencil']" />
                    </Button>
                    <Button
                        type="button"
                        variant="outline-danger"
                        size="sm"
                        :aria-label="$t('settings.type_actions.delete')"
                        @click="askDelete(type)"
                    >
                        <Icon :name="['fas', 'trash-can']" />
                    </Button>
                </div>
            </div>
        </Card>
        <div
            v-if="draggedId !== null && activeDropIndex === orderedTypes.length"
            class="min-h-[72px] rounded-lg border-2 border-dashed border-primary bg-primary/10"
            @dragover.prevent
            @drop.prevent="dropType"
        />
    </div>
    <Dialog
        :open="Boolean(deleting)"
        :title="$t('settings.type_actions.delete_title')"
        :confirm-label="$t('settings.type_actions.delete')"
        confirm-variant="danger"
        :busy="deleteBusy"
        @update:open="deleting = null"
        @confirm="confirmDelete"
    >
        <template #description>
            {{ deleteBody }}
            <strong class="mt-2 block text-charcoal">
                {{ $t('settings.type_actions.delete_irreversible') }}
            </strong>
        </template>
    </Dialog>
</template>
