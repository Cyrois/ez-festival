<script setup>
import { Badge } from '../ui/badge';
import { Button } from '../ui/button';
import { Card } from '../ui/card';
import { FormField } from '../ui/form-field';
import { Icon } from '../ui/icon';
import { IconButton } from '../ui/icon-button';
import { Textarea } from '../ui/textarea';
import { teamNoteEditState } from '../../lib/teamNoteEditWindow';
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from 'vue';
import { trans } from 'laravel-vue-i18n';

const props = defineProps({
    notes: { type: Array, default: () => [] },
    newNotes: { type: Array, required: true },
    noteEdits: { type: Array, required: true },
    canWrite: { type: Boolean, required: true },
    timezone: { type: String, default: 'America/Vancouver' },
    errors: { type: Object, default: () => ({}) },
});

const emit = defineEmits(['update:newNotes', 'update:noteEdits']);

const composing = ref(false);
const newBody = ref('');
const newBodyError = ref('');
const editingId = ref(null);
const editingBody = ref('');
const editingError = ref('');
const now = ref(Date.now());
let pendingId = 0;
let clock;

const editsById = computed(
    () => new Map(props.noteEdits.map((edit) => [edit.id, edit])),
);
const visibleNotes = computed(() => [
    ...props.newNotes.map((note) => ({ ...note, pending: true })),
    ...props.notes,
]);

const openCompose = async () => {
    if (!props.canWrite) return;
    composing.value = true;
    newBodyError.value = '';
    await nextTick();
    document.querySelector('[data-team-note-compose]')?.focus();
};

const cancelCompose = () => {
    composing.value = false;
    newBody.value = '';
    newBodyError.value = '';
};

const validateBody = (body) => {
    const value = body.trim();

    if (!value) return trans('team.member.notes.errors.body_required');
    if (value.length > 5000) return trans('team.member.notes.errors.body_max');

    return '';
};

const addNote = () => {
    const error = validateBody(newBody.value);
    if (error) {
        newBodyError.value = error;
        return;
    }

    emit('update:newNotes', [
        { client_id: `new-${++pendingId}`, body: newBody.value.trim() },
        ...props.newNotes,
    ]);
    cancelCompose();
};

const removeNewNote = (clientId) => {
    emit(
        'update:newNotes',
        props.newNotes.filter((note) => note.client_id !== clientId),
    );
};

const canEdit = (note) =>
    teamNoteEditState({
        note,
        now: now.value,
        canWrite: props.canWrite,
        editingId: editingId.value,
    }).showEdit;

const openEdit = async (note) => {
    if (!canEdit(note)) return;
    editingId.value = note.id;
    editingBody.value = editsById.value.get(note.id)?.body ?? note.body;
    editingError.value = '';
    await nextTick();
    document.querySelector(`[data-team-note-edit="${note.id}"]`)?.focus();
};

const cancelEdit = () => {
    editingId.value = null;
    editingBody.value = '';
    editingError.value = '';
};

const finishEdit = (note) => {
    if (!canEdit(note)) {
        cancelEdit();
        return;
    }

    const error = validateBody(editingBody.value);
    if (error) {
        editingError.value = error;
        return;
    }

    const edit = { id: note.id, body: editingBody.value.trim() };
    if (edit.body === note.body) {
        emit(
            'update:noteEdits',
            props.noteEdits.filter((item) => item.id !== note.id),
        );
        cancelEdit();

        return;
    }

    emit('update:noteEdits', [
        ...props.noteEdits.filter((item) => item.id !== note.id),
        edit,
    ]);
    cancelEdit();
};

const noteBody = (note) => editsById.value.get(note.id)?.body ?? note.body;

const formatNoteTime = (iso) => {
    if (!iso) return '';
    try {
        return new Intl.DateTimeFormat('en-US', {
            month: 'short',
            day: 'numeric',
            year: 'numeric',
            hour: 'numeric',
            minute: '2-digit',
            timeZone: props.timezone,
        }).format(new Date(iso));
    } catch {
        return iso;
    }
};

watch(now, () => {
    if (editingId.value === null) return;
    const note = props.notes.find((item) => item.id === editingId.value);
    if (
        !note ||
        teamNoteEditState({
            note,
            now: now.value,
            canWrite: props.canWrite,
            editingId: editingId.value,
        }).shouldCloseOpenEdit
    ) {
        cancelEdit();
    }
});

onMounted(() => {
    clock = window.setInterval(() => {
        now.value = Date.now();
    }, 1000);
});
onUnmounted(() => window.clearInterval(clock));
</script>

<template>
    <Card>
        <div class="flex items-start justify-between gap-3">
            <h2 class="m-0 text-xl font-bold tracking-tight text-muted">
                {{ $t('team.member.note_log') }}
            </h2>
            <Button
                v-if="canWrite"
                type="button"
                size="sm"
                :disabled="composing"
                @click="openCompose"
            >
                <Icon
                    :name="['fas', 'plus']"
                    class="mr-1.5"
                    size="sm"
                />
                {{ $t('team.member.new_note') }}
            </Button>
        </div>
        <p class="mt-1 mb-4 text-xs text-muted">
            {{
                $t(
                    canWrite
                        ? 'team.member.note_log_hint'
                        : 'team.member.note_log_hint_locked',
                )
            }}
        </p>

        <div
            v-if="composing"
            class="mb-3 rounded-[10px] border border-primary bg-primary/10 p-3"
        >
            <FormField
                v-slot="{ id, invalid }"
                :label="$t('team.member.new_note')"
                :error="newBodyError || errors['notes.0.body']"
            >
                <div class="flex flex-col gap-2 sm:flex-row sm:items-end">
                    <Textarea
                        :id="id"
                        v-model="newBody"
                        class="min-h-[70px] flex-1 bg-ground"
                        data-team-note-compose
                        :invalid="invalid"
                        :placeholder="$t('team.member.note_placeholder')"
                        maxlength="5000"
                    />
                    <div class="flex flex-col gap-1.5 sm:shrink-0">
                        <Button
                            type="button"
                            size="sm"
                            class="w-full sm:w-auto"
                            @click="addNote"
                        >
                            {{ $t('team.member.add_note') }}
                        </Button>
                        <Button
                            type="button"
                            size="sm"
                            variant="outline"
                            class="w-full sm:w-auto"
                            @click="cancelCompose"
                        >
                            {{ $t('setup.actions.cancel') }}
                        </Button>
                    </div>
                </div>
            </FormField>
        </div>

        <div class="space-y-2">
            <div
                v-for="note in visibleNotes"
                :key="note.pending ? note.client_id : note.id"
                :class="[
                    'rounded-lg border p-3',
                    note.pending
                        ? 'border-l-4 border-primary/30 border-l-primary bg-primary-soft/50'
                        : 'border-line bg-page',
                ]"
            >
                <div
                    class="flex flex-wrap items-center justify-between gap-2 text-xs"
                >
                    <div class="flex flex-wrap items-center gap-2">
                        <Badge
                            v-if="!note.pending && editingId === note.id"
                            pill
                            variant="warning"
                        >
                            {{ $t('team.member.notes.editing') }}
                        </Badge>
                        <strong class="font-bold">
                            {{
                                note.pending
                                    ? $t('team.member.notes.pending_author')
                                    : note.author
                            }}
                        </strong>
                        <span
                            v-if="note.pending"
                            aria-hidden="true"
                            class="h-3 border-l border-primary/30"
                        />
                        <span
                            v-if="note.pending"
                            class="text-muted"
                        >
                            {{ $t('team.member.notes.pending') }}
                        </span>
                    </div>
                    <div
                        v-if="!note.pending"
                        class="flex items-center gap-2 text-muted"
                    >
                        <span>{{ formatNoteTime(note.created_at) }}</span>
                        <span v-if="note.edited_at">
                            {{ $t('team.member.notes.edited') }}
                        </span>
                        <IconButton
                            v-if="canEdit(note)"
                            :icon="['fas', 'pen']"
                            :label="$t('team.member.notes.edit')"
                            tone="edit"
                            @click="openEdit(note)"
                        />
                    </div>
                    <IconButton
                        v-else
                        :icon="['fas', 'xmark']"
                        :label="$t('team.member.notes.remove')"
                        tone="delete"
                        @click="removeNewNote(note.client_id)"
                    />
                </div>

                <FormField
                    v-if="editingId === note.id"
                    v-slot="{ id, invalid }"
                    class="mt-2"
                    :error="editingError"
                >
                    <Textarea
                        :id="id"
                        v-model="editingBody"
                        :data-team-note-edit="note.id"
                        :invalid="invalid"
                        maxlength="5000"
                    />
                    <div class="mt-2 flex justify-end gap-2">
                        <Button
                            type="button"
                            size="sm"
                            variant="outline"
                            @click="cancelEdit"
                        >
                            {{ $t('setup.actions.cancel') }}
                        </Button>
                        <Button
                            type="button"
                            size="sm"
                            @click="finishEdit(note)"
                        >
                            {{ $t('team.member.notes.done') }}
                        </Button>
                    </div>
                </FormField>
                <template v-else-if="note.pending">
                    <p class="mt-1 mb-0 text-sm whitespace-pre-wrap">
                        {{ note.body }}
                    </p>
                </template>
                <template v-else>
                    <p class="mt-1 mb-0 text-sm whitespace-pre-wrap">
                        {{ noteBody(note) }}
                    </p>
                    <p
                        v-if="!note.pending && editsById.has(note.id)"
                        class="mt-2 mb-0 text-xs font-semibold text-warning"
                    >
                        {{ $t('team.member.notes.edit_pending') }}
                    </p>
                </template>
            </div>
            <p
                v-if="!visibleNotes.length"
                class="m-0 py-3 text-sm text-muted"
            >
                {{ $t('team.member.notes_empty') }}
            </p>
        </div>
    </Card>
</template>
