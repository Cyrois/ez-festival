<script setup>
import { Button } from '../ui/button';
import { Card } from '../ui/card';
import { FormField } from '../ui/form-field';
import { Icon } from '../ui/icon';
import { Textarea } from '../ui/textarea';
import { useFlashToast } from '../../composables/useFlashToast';
import { toastFormErrors } from '../../lib/fieldError';
import { useForm } from '@inertiajs/vue3';
import { nextTick, ref } from 'vue';

const props = defineProps({
    notes: { type: Array, required: true },
    postUrl: { type: String, required: true },
    canWrite: { type: Boolean, required: true },
    timezone: { type: String, default: 'America/Vancouver' },
    translationNamespace: { type: String, required: true },
});

const noteForm = useForm({
    body: '',
});
const composing = ref(false);
const { showError, showFormError } = useFlashToast();
const translationKey = (key) => `${props.translationNamespace}.${key}`;

const openCompose = async () => {
    if (!props.canWrite) {
        return;
    }
    composing.value = true;
    await nextTick();
    document.querySelector('[data-note-compose]')?.focus();
};

const cancelCompose = () => {
    composing.value = false;
    noteForm.reset('body');
    noteForm.clearErrors();
};

const postNote = () => {
    if (!props.canWrite) {
        return;
    }
    noteForm.post(props.postUrl, {
        preserveScroll: true,
        onError: (errors) =>
            toastFormErrors(noteForm, errors, { showError, showFormError }),
        onSuccess: () => {
            noteForm.reset('body');
            composing.value = false;
        },
    });
};

const formatNoteTime = (iso) => {
    if (!iso) {
        return '';
    }
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
</script>

<template>
    <Card>
        <div class="flex items-start justify-between gap-3">
            <h2 class="m-0 text-xl font-bold tracking-tight text-muted">
                {{ $t(translationKey('note_log')) }}
            </h2>
            <Button
                v-if="canWrite"
                size="sm"
                :disabled="composing || noteForm.processing"
                @click="openCompose"
            >
                <Icon
                    :name="['fas', 'plus']"
                    class="mr-1.5"
                    size="sm"
                />
                {{ $t(translationKey('new_note')) }}
            </Button>
        </div>
        <p class="mt-1 mb-4 text-xs text-muted">
            {{ $t(translationKey('note_log_hint')) }}
        </p>
        <div>
            <div
                v-if="composing"
                class="mb-3 rounded-[10px] border border-primary bg-primary/10 p-3"
            >
                <FormField
                    v-slot="{ id, invalid }"
                    :label="$t(translationKey('new_note'))"
                    :error="noteForm.errors.body"
                >
                    <div class="flex flex-col gap-2 sm:flex-row sm:items-end">
                        <Textarea
                            :id="id"
                            v-model="noteForm.body"
                            class="min-h-16 flex-1 bg-ground"
                            data-note-compose
                            :invalid="invalid"
                            :disabled="noteForm.processing"
                            :placeholder="
                                $t(translationKey('note_placeholder'))
                            "
                            maxlength="5000"
                            required
                        />
                        <div class="flex flex-col gap-1.5 sm:shrink-0">
                            <Button
                                size="sm"
                                class="w-full sm:w-auto"
                                :loading="noteForm.processing"
                                @click="postNote"
                            >
                                {{ $t(translationKey('post_note')) }}
                            </Button>
                            <Button
                                size="sm"
                                variant="outline"
                                class="w-full sm:w-auto"
                                :disabled="noteForm.processing"
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
                    v-for="note in notes"
                    :key="note.id"
                    class="rounded-lg border border-line bg-page p-3"
                >
                    <div class="flex justify-between gap-3 text-xs">
                        <strong class="font-bold">
                            {{
                                note.author ||
                                $t(translationKey('notes_author_unknown'))
                            }}
                        </strong>
                        <span class="text-muted">
                            {{ formatNoteTime(note.created_at) }}
                        </span>
                    </div>
                    <p class="mt-1 mb-0 text-sm whitespace-pre-wrap">
                        {{ note.body }}
                    </p>
                </div>
                <p
                    v-if="!notes.length"
                    class="m-0 py-3 text-sm text-muted"
                >
                    {{ $t(translationKey('notes_empty')) }}
                </p>
            </div>
        </div>
    </Card>
</template>
