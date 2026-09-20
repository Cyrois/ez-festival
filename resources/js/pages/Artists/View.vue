<script setup>
import AppLayout from '../../layouts/AppLayout.vue';
import { Avatar } from '../../components/ui/avatar';
import { Button } from '../../components/ui/button';
import { Card } from '../../components/ui/card';
import EngagementPeoplePanel from '../../components/people/EngagementPeoplePanel.vue';
import PassAssignmentsPanel from '../../components/credentials/PassAssignmentsPanel.vue';
import { Checkbox } from '../../components/ui/checkbox';
import { FormField } from '../../components/ui/form-field';
import { Icon } from '../../components/ui/icon';
import { Input } from '../../components/ui/input';
import { Select } from '../../components/ui/select';
import { Tag } from '../../components/ui/tag';
import { Textarea } from '../../components/ui/textarea';
import { useFlashToast } from '../../composables/useFlashToast';
import { toastFormErrors } from '../../lib/fieldError';
import { useForm } from '@inertiajs/vue3';
import { computed, nextTick, ref } from 'vue';
import { trans } from 'laravel-vue-i18n';

const props = defineProps({
    engagement: { type: Object, required: true },
    notes: { type: Array, required: true },
    event: { type: Object, required: true },
    types: { type: Array, required: true },
    labels: { type: Array, required: true },
    statuses: { type: Array, required: true },
    labelColors: { type: Array, required: true },
    passes: { type: Array, default: () => [] },
    canWrite: { type: Boolean, required: true },
});

const form = useForm({
    name: props.engagement.name,
    status: props.engagement.status,
    artist_type_id: props.engagement.artist_type_id ?? '',
    label_ids: props.engagement.labels.map((label) => label.id),
    new_labels: [],
});

const noteForm = useForm({
    body: '',
});

const composing = ref(false);
const { showError, showFormError } = useFlashToast();

const breadcrumbs = computed(() => [
    { label: trans('app.name'), href: '/dashboard' },
    { label: trans('nav.artists'), href: '/artists/advancing' },
    { label: trans('artists.advancing'), href: '/artists/advancing' },
    { label: trans('artists.view') },
]);

const readOnly = computed(() => !props.canWrite);

const toggleLabel = (id) => {
    if (readOnly.value || form.processing) {
        return;
    }
    form.label_ids = form.label_ids.includes(id)
        ? form.label_ids.filter((value) => value !== id)
        : [...form.label_ids, id];
};

const submit = () => {
    if (readOnly.value) {
        return;
    }
    form.put(`/artists/engagements/${props.engagement.id}`, {
        onError: (errors) =>
            toastFormErrors(form, errors, { showError, showFormError }),
    });
};

const openCompose = async () => {
    if (readOnly.value) {
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
    if (readOnly.value) {
        return;
    }
    noteForm.post(`/artists/engagements/${props.engagement.id}/notes`, {
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
            timeZone: props.event.timezone || 'America/Vancouver',
        }).format(new Date(iso));
    } catch {
        return iso;
    }
};
</script>

<template>
    <AppLayout
        :title="engagement.name"
        :breadcrumbs="breadcrumbs"
        back-href="/artists/advancing"
        :back-label="$t('artists.back_to_advancing')"
    >
        <div class="mx-auto max-w-6xl pb-24">
            <div class="mb-5 flex items-center gap-3.5">
                <Avatar
                    :name="engagement.name"
                    size="lg"
                />
                <h1 class="m-0 text-[26px] font-bold tracking-tight">
                    {{ engagement.name }}
                </h1>
            </div>
            <p class="mt-0 mb-5 text-sm text-muted">
                {{ $t('artists.view_lead', { name: event.name }) }}
            </p>

            <p
                v-if="readOnly"
                class="mb-4 flex items-center gap-2 rounded-lg border border-warning/20 bg-warning/10 p-3 text-sm text-charcoal"
                role="status"
            >
                <Icon :name="['fas', 'lock']" />
                {{ $t('artists.view_locked') }}
            </p>

            <div class="grid items-stretch gap-4 lg:grid-cols-2">
                <Card>
                    <h2 class="m-0 text-xl font-bold text-muted">
                        {{ $t('artists.details') }}
                    </h2>
                    <p class="mt-1 mb-4 text-xs text-muted">
                        {{ $t('artists.details_hint') }}
                    </p>
                    <form
                        id="artist-details"
                        class="space-y-4"
                        @submit.prevent="submit"
                    >
                        <FormField
                            v-slot="{ id, invalid }"
                            :label="$t('artists.name')"
                            :error="form.errors.name"
                            required
                        >
                            <Input
                                :id="id"
                                v-model="form.name"
                                :invalid="invalid"
                                :disabled="readOnly || form.processing"
                                maxlength="255"
                                required
                            />
                        </FormField>
                        <div class="grid gap-4 sm:grid-cols-2">
                            <FormField
                                v-slot="{ id, invalid }"
                                :label="$t('artists.columns.status')"
                                :error="form.errors.status"
                            >
                                <Select
                                    :id="id"
                                    v-model="form.status"
                                    :invalid="invalid"
                                    :disabled="readOnly || form.processing"
                                >
                                    <option
                                        v-for="status in statuses"
                                        :key="status"
                                        :value="status"
                                    >
                                        {{ $t(`artists.status.${status}`) }}
                                    </option>
                                </Select>
                            </FormField>
                            <FormField
                                v-slot="{ id, invalid }"
                                :label="$t('artists.columns.type')"
                                :error="form.errors.artist_type_id"
                            >
                                <Select
                                    :id="id"
                                    v-model="form.artist_type_id"
                                    :invalid="invalid"
                                    :disabled="readOnly || form.processing"
                                >
                                    <option value="">
                                        {{ $t('artists.type_optional') }}
                                    </option>
                                    <option
                                        v-for="type in types"
                                        :key="type.id"
                                        :value="type.id"
                                    >
                                        {{ type.name }}
                                    </option>
                                </Select>
                            </FormField>
                        </div>
                        <fieldset class="m-0 min-w-0 space-y-3 border-0 p-0">
                            <legend class="mb-2 text-xs font-bold">
                                {{ $t('artists.columns.labels') }}
                            </legend>
                            <p class="m-0 text-xs text-muted">
                                {{ $t('artists.labels_hint') }}
                            </p>
                            <div class="flex flex-wrap gap-3">
                                <Checkbox
                                    v-for="label in labels"
                                    :key="label.id"
                                    :model-value="
                                        form.label_ids.includes(label.id)
                                    "
                                    :disabled="readOnly || form.processing"
                                    @update:model-value="toggleLabel(label.id)"
                                >
                                    <Tag
                                        :name="label.name"
                                        :color="label.color"
                                    />
                                </Checkbox>
                            </div>
                            <p
                                v-if="form.errors.label_ids"
                                class="text-xs text-danger"
                                role="alert"
                            >
                                {{ form.errors.label_ids }}
                            </p>
                            <div
                                v-for="(label, index) in form.new_labels"
                                :key="index"
                                class="grid items-start gap-3 rounded-lg border border-line bg-page p-3 sm:grid-cols-[1fr_10rem_auto]"
                            >
                                <FormField
                                    v-slot="{ id, invalid }"
                                    :label="$t('artists.label_name')"
                                    :error="
                                        form.errors[`new_labels.${index}.name`]
                                    "
                                >
                                    <Input
                                        :id="id"
                                        v-model="label.name"
                                        :invalid="invalid"
                                        :disabled="readOnly || form.processing"
                                        maxlength="255"
                                        required
                                    />
                                </FormField>
                                <FormField
                                    v-slot="{ id, invalid }"
                                    :label="$t('artists.label_color')"
                                    :error="
                                        form.errors[`new_labels.${index}.color`]
                                    "
                                >
                                    <Select
                                        :id="id"
                                        v-model="label.color"
                                        :invalid="invalid"
                                        :disabled="readOnly || form.processing"
                                    >
                                        <option
                                            v-for="color in labelColors"
                                            :key="color"
                                            :value="color"
                                        >
                                            {{ $t(`artists.colors.${color}`) }}
                                        </option>
                                    </Select>
                                </FormField>
                                <Button
                                    type="button"
                                    variant="ghost"
                                    class="sm:mt-5"
                                    :aria-label="
                                        $t('artists.remove_label', {
                                            name: label.name,
                                        })
                                    "
                                    :disabled="readOnly || form.processing"
                                    @click="form.new_labels.splice(index, 1)"
                                >
                                    <Icon :name="['fas', 'trash']" />
                                </Button>
                            </div>
                            <Button
                                v-if="!readOnly"
                                type="button"
                                variant="outline"
                                size="sm"
                                :disabled="
                                    form.processing ||
                                    form.new_labels.length >= 20
                                "
                                @click="
                                    form.new_labels.push({
                                        name: '',
                                        color: 'primary',
                                    })
                                "
                            >
                                <Icon
                                    :name="['fas', 'plus']"
                                    class="mr-2"
                                    size="sm"
                                />
                                {{ $t('artists.create_label') }}
                            </Button>
                        </fieldset>
                    </form>
                </Card>
                <Card class="flex flex-col">
                    <EngagementPeoplePanel
                        :people="engagement.people"
                        :base-path="`/artists/engagements/${engagement.id}`"
                        :can-write="canWrite"
                    />
                </Card>
            </div>

            <Card class="mt-4">
                <h2 class="m-0 text-xl font-bold text-muted">
                    {{ $t('artists.custom_fields') }}
                </h2>
                <div
                    class="mt-3 rounded-lg border border-dashed border-line bg-page p-5 text-center text-sm text-muted"
                >
                    {{ $t('artists.custom_fields_shell') }}
                </div>
            </Card>

            <Card class="mt-4">
                <PassAssignmentsPanel
                    :assignments="engagement.pass_assignments"
                    :people="engagement.people"
                    :passes="passes"
                    :base-path="`/artists/engagements/${engagement.id}`"
                    :can-write="canWrite"
                />
            </Card>

            <Card class="mt-4">
                <h2 class="m-0 text-xl font-bold text-muted">
                    {{ $t('artists.contracts_phase') }}
                </h2>
                <div
                    class="mt-3 rounded-lg border border-dashed border-line bg-page p-5 text-center text-sm text-muted"
                >
                    {{ $t('artists.contracts_phase') }}
                </div>
            </Card>

            <Card class="mt-4">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <h2
                            class="m-0 text-xl font-bold tracking-tight text-muted"
                        >
                            {{ $t('artists.note_log') }}
                        </h2>
                    </div>
                    <Button
                        v-if="!readOnly"
                        size="sm"
                        :disabled="composing || noteForm.processing"
                        @click="openCompose"
                    >
                        <Icon
                            :name="['fas', 'plus']"
                            class="mr-1.5"
                            size="sm"
                        />
                        {{ $t('artists.new_note') }}
                    </Button>
                </div>
                <p class="mt-1 mb-4 text-xs text-muted">
                    {{ $t('artists.note_log_hint') }}
                </p>
                <div>
                    <div
                        v-if="composing"
                        class="mb-3 rounded-[10px] border border-primary bg-primary/10 p-3"
                    >
                        <FormField
                            v-slot="{ id, invalid }"
                            :label="$t('artists.new_note')"
                            :error="noteForm.errors.body"
                        >
                            <div
                                class="flex flex-col gap-2 sm:flex-row sm:items-end"
                            >
                                <Textarea
                                    :id="id"
                                    v-model="noteForm.body"
                                    class="min-h-16 flex-1 bg-ground"
                                    data-note-compose
                                    :invalid="invalid"
                                    :disabled="noteForm.processing"
                                    :placeholder="
                                        $t('artists.note_placeholder')
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
                                        {{ $t('artists.post_note') }}
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
                                <strong class="font-bold">{{
                                    note.author ||
                                    $t('artists.notes_author_unknown')
                                }}</strong>
                                <span class="text-muted">{{
                                    formatNoteTime(note.created_at)
                                }}</span>
                            </div>
                            <p class="mt-1 mb-0 text-sm whitespace-pre-wrap">
                                {{ note.body }}
                            </p>
                        </div>
                        <p
                            v-if="!notes.length"
                            class="m-0 py-3 text-sm text-muted"
                        >
                            {{ $t('artists.notes_empty') }}
                        </p>
                    </div>
                </div>
            </Card>

            <div
                v-if="!readOnly"
                class="fixed right-0 bottom-0 left-0 z-30 border-t border-line bg-ground/95 py-3 backdrop-blur lg:left-56"
            >
                <div class="container mx-auto px-4 md:px-6">
                    <div
                        class="mx-auto flex max-w-6xl items-center justify-between"
                    >
                        <Button
                            href="/artists/advancing"
                            variant="cancel"
                            :disabled="form.processing"
                        >
                            {{ $t('setup.actions.cancel') }}
                        </Button>
                        <Button
                            form="artist-details"
                            type="submit"
                            :loading="form.processing"
                        >
                            {{ $t('artists.save_details') }}
                        </Button>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
