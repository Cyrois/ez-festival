<script setup>
import AppLayout from '../../layouts/AppLayout.vue';
import { Avatar } from '../../components/ui/avatar';
import { Button } from '../../components/ui/button';
import { Card } from '../../components/ui/card';
import { Checkbox } from '../../components/ui/checkbox';
import { FormField } from '../../components/ui/form-field';
import { Icon } from '../../components/ui/icon';
import { Input } from '../../components/ui/input';
import { Select } from '../../components/ui/select';
import { Tag } from '../../components/ui/tag';
import { Textarea } from '../../components/ui/textarea';
import { useFlashToast } from '../../composables/useFlashToast';
import { useForm } from '@inertiajs/vue3';
import { computed, nextTick } from 'vue';
import { trans } from 'laravel-vue-i18n';

const props = defineProps({
    engagement: { type: Object, required: true },
    notes: { type: Array, required: true },
    event: { type: Object, required: true },
    types: { type: Array, required: true },
    labels: { type: Array, required: true },
    statuses: { type: Array, required: true },
    labelColors: { type: Array, required: true },
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
const { showFormError } = useFlashToast();

const breadcrumbs = computed(() => [
    { label: trans('app.name'), href: '/dashboard' },
    { label: trans('nav.artists'), href: '/artists/advancing' },
    { label: trans('artists.advancing'), href: '/artists/advancing' },
    { label: trans('artists.edit') },
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
        onError: showFormError,
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
        onError: showFormError,
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
        <div class="mb-5 flex flex-wrap items-center gap-3.5">
            <Avatar
                :name="engagement.name"
                size="lg"
            />
            <h1 class="m-0 text-[26px] font-bold tracking-tight">
                {{ engagement.name }}
            </h1>
        </div>
        <p class="mt-0 mb-4.5 text-sm text-muted">
            {{ $t('artists.edit_lead', { name: event.name }) }}
        </p>

        <p
            v-if="readOnly"
            class="mb-4 flex items-center gap-2 rounded-lg border border-warning/20 bg-warning/10 p-3 text-sm text-charcoal"
            role="status"
        >
            <Icon :name="['fas', 'lock']" />
            {{ $t('artists.edit_locked') }}
        </p>

        <div class="grid items-start gap-4.5 lg:grid-cols-2">
            <Card class="flex min-h-[32rem] flex-col">
                <h2
                    class="mt-0 mb-1 text-xl font-bold tracking-tight text-muted"
                >
                    {{ $t('artists.details') }}
                </h2>
                <p class="mt-0 mb-3.5 text-xs text-muted">
                    {{ $t('artists.details_hint') }}
                </p>
                <form
                    class="flex flex-1 flex-col space-y-5"
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
                    <div class="grid gap-5 sm:grid-cols-2">
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
                                :model-value="form.label_ids.includes(label.id)"
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
                                :error="form.errors[`new_labels.${index}.name`]"
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
                            variant="outline"
                            size="sm"
                            :disabled="
                                form.processing || form.new_labels.length >= 20
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
                    <div>
                        <p class="mb-2 text-xs font-bold">
                            {{ $t('artists.custom_fields') }}
                        </p>
                        <div
                            class="rounded-lg border border-dashed border-line bg-page p-3 text-xs leading-snug text-muted"
                        >
                            {{ $t('artists.custom_fields_shell') }}
                        </div>
                    </div>
                    <p
                        class="m-0 text-[11px] font-bold tracking-wider text-muted uppercase"
                    >
                        {{ $t('artists.contracts_phase') }}
                    </p>
                    <div
                        class="mt-auto flex justify-end gap-2.5 border-t border-line pt-4"
                    >
                        <Button
                            href="/artists/advancing"
                            variant="outline"
                            :disabled="form.processing"
                        >
                            {{ $t('setup.actions.cancel') }}
                        </Button>
                        <Button
                            v-if="!readOnly"
                            type="submit"
                            :loading="form.processing"
                        >
                            {{ $t('artists.save_details') }}
                        </Button>
                    </div>
                </form>
            </Card>

            <Card class="flex min-h-[32rem] flex-col">
                <div class="mb-1 flex items-start justify-between gap-3">
                    <h2 class="m-0 text-xl font-bold tracking-tight text-muted">
                        {{ $t('artists.note_log') }}
                    </h2>
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
                <p class="mt-0 mb-3.5 text-xs text-muted">
                    {{ $t('artists.note_log_hint') }}
                </p>
                <div class="flex min-h-0 flex-1 flex-col">
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
                    <div
                        class="flex max-h-[25rem] flex-1 flex-col gap-3 overflow-y-auto"
                    >
                        <div
                            v-for="note in notes"
                            :key="note.id"
                            class="rounded-[10px] border border-line bg-page p-3"
                        >
                            <div
                                class="mb-1.5 flex flex-wrap items-baseline justify-between gap-2 text-xs"
                            >
                                <strong class="font-bold">{{
                                    note.author
                                }}</strong>
                                <span class="text-muted">{{
                                    formatNoteTime(note.created_at)
                                }}</span>
                            </div>
                            <p
                                class="m-0 text-[13px] leading-snug whitespace-pre-wrap"
                            >
                                {{ note.body }}
                            </p>
                        </div>
                        <p
                            v-if="!notes.length"
                            class="m-0 py-8 text-center text-sm text-muted"
                        >
                            {{ $t('artists.notes_empty') }}
                        </p>
                    </div>
                </div>
            </Card>
        </div>
    </AppLayout>
</template>
