<script setup>
import AppLayout from '../../layouts/AppLayout.vue';
import { Avatar } from '../../components/ui/avatar';
import { Button } from '../../components/ui/button';
import { Card } from '../../components/ui/card';
import EngagementPeoplePanel from '../../components/people/EngagementPeoplePanel.vue';
import { Checkbox } from '../../components/ui/checkbox';
import { CustomDropdown } from '../../components/ui/custom-dropdown';
import { FormField } from '../../components/ui/form-field';
import { Icon } from '../../components/ui/icon';
import { IconButton } from '../../components/ui/icon-button';
import { Input } from '../../components/ui/input';
import { Select } from '../../components/ui/select';
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
    statuses: { type: Array, required: true },
    passes: { type: Array, default: () => [] },
    customFields: { type: Array, default: () => [] },
    canWrite: { type: Boolean, required: true },
});
const form = useForm({
    name: props.engagement.name,
    status: props.engagement.status,
    vendor_type_id: props.engagement.vendor_type_id ?? '',
    custom_fields: Object.fromEntries(
        props.customFields.map((field) => [
            field.id,
            props.engagement.custom?.[field.id] ??
                (field.type === 'checkbox' ? false : ''),
        ]),
    ),
    pass_assignments: props.engagement.pass_assignments.map((assignment) => ({
        id: assignment.id,
        pass_type_id: assignment.pass_type_id,
        person_id: assignment.person?.id ?? null,
    })),
    notes: [],
});
const composing = ref(false);
const draftNote = ref('');
const { showError, showFormError } = useFlashToast();
const readOnly = computed(() => !props.canWrite);
const breadcrumbs = computed(() => [
    { label: trans('app.name'), href: '/dashboard' },
    { label: trans('nav.vendors'), href: '/vendors/advancing' },
    { label: trans('nav.vendors.advancing'), href: '/vendors/advancing' },
    { label: trans('vendors.view') },
]);
const allNotes = computed(() => [
    ...form.notes.map((note, index) => ({
        ...note,
        id: `draft-${index}`,
        author: trans('vendors.notes_pending_author'),
        created_at: null,
    })),
    ...props.notes,
]);
const passItems = computed(() =>
    props.passes.map((pass) => ({
        value: pass.id,
        title: pass.name,
    })),
);
const contactItems = computed(() => [
    {
        value: null,
        title: trans('credentials.assignments.unassigned'),
    },
    ...props.engagement.people.map((person) => ({
        value: person.id,
        title: person.name,
    })),
]);
const save = () =>
    !readOnly.value &&
    form.put(`/vendors/engagements/${props.engagement.id}`, {
        onError: (errors) =>
            toastFormErrors(form, errors, { showError, showFormError }),
    });
const addNote = async () => {
    composing.value = true;
    await nextTick();
    document.querySelector('[data-note-compose]')?.focus();
};
const stageNote = () => {
    if (draftNote.value.trim()) {
        form.notes.unshift({ body: draftNote.value.trim() });
        draftNote.value = '';
        composing.value = false;
    }
};
const addPass = () => {
    if (props.passes[0])
        form.pass_assignments.push({
            pass_type_id: props.passes[0].id,
            person_id: null,
        });
};
const noteTime = (iso) =>
    iso
        ? new Intl.DateTimeFormat('en-US', {
              month: 'short',
              day: 'numeric',
              year: 'numeric',
              hour: 'numeric',
              minute: '2-digit',
              timeZone: props.event.timezone || 'America/Vancouver',
          }).format(new Date(iso))
        : trans('vendors.notes_pending');
</script>

<template>
    <AppLayout
        :title="engagement.name"
        :breadcrumbs="breadcrumbs"
        back-href="/vendors/advancing"
        :back-label="$t('vendors.back_to_advancing')"
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
                {{ $t('vendors.view_lead', { name: event.name }) }}
            </p>
            <p
                v-if="readOnly"
                class="mb-4 flex items-center gap-2 rounded-lg border border-warning/20 bg-warning/10 p-3 text-sm text-charcoal"
            >
                <Icon :name="['fas', 'lock']" />{{ $t('vendors.view_locked') }}
            </p>
            <form
                class="space-y-4"
                @submit.prevent="save"
            >
                <div class="grid items-stretch gap-4 lg:grid-cols-2">
                    <Card
                        ><h2 class="m-0 text-xl font-bold text-muted">
                            {{ $t('vendors.details') }}
                        </h2>
                        <p class="mt-1 mb-4 text-xs text-muted">
                            {{ $t('vendors.details_hint') }}
                        </p>
                        <div class="space-y-4">
                            <FormField
                                v-slot="{ id, invalid }"
                                :label="$t('vendors.name')"
                                :error="form.errors.name"
                                required
                                ><Input
                                    :id="id"
                                    v-model="form.name"
                                    :invalid="invalid"
                                    :disabled="readOnly"
                                    required /></FormField
                            ><FormField
                                v-slot="{ id, invalid }"
                                :label="$t('vendors.columns.status')"
                                :error="form.errors.status"
                                ><Select
                                    :id="id"
                                    v-model="form.status"
                                    :invalid="invalid"
                                    :disabled="readOnly"
                                    ><option
                                        v-for="status in statuses"
                                        :key="status"
                                        :value="status"
                                    >
                                        {{ $t(`vendors.status.${status}`) }}
                                    </option></Select
                                ></FormField
                            ><FormField
                                v-slot="{ id, invalid }"
                                :label="$t('vendors.columns.type')"
                                :error="form.errors.vendor_type_id"
                                ><Select
                                    :id="id"
                                    v-model="form.vendor_type_id"
                                    :invalid="invalid"
                                    :disabled="readOnly"
                                    ><option value="">
                                        {{ $t('vendors.type_optional') }}
                                    </option>
                                    <option
                                        v-for="type in types"
                                        :key="type.id"
                                        :value="type.id"
                                    >
                                        {{ type.name }}
                                    </option></Select
                                ></FormField
                            >
                        </div></Card
                    >
                    <Card class="flex flex-col">
                        <EngagementPeoplePanel
                            :people="engagement.people"
                            :base-path="`/vendors/engagements/${engagement.id}`"
                            :can-write="canWrite"
                        />
                    </Card>
                </div>
                <Card v-if="customFields.length"
                    ><h2 class="m-0 text-xl font-bold text-muted">
                        {{ $t('vendors.custom_fields.title') }}
                    </h2>
                    <p class="mt-1 mb-4 text-xs text-muted">
                        {{ $t('vendors.custom_fields.lead') }}
                    </p>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <FormField
                            v-for="field in customFields"
                            :key="field.id"
                            v-slot="{ id, invalid }"
                            :label="field.label"
                            :error="form.errors[`custom_fields.${field.id}`]"
                            :required="field.required"
                            ><Checkbox
                                v-if="field.type === 'checkbox'"
                                :id="id"
                                v-model="form.custom_fields[field.id]"
                                :disabled="readOnly" /><Textarea
                                v-else-if="field.type === 'textarea'"
                                :id="id"
                                v-model="form.custom_fields[field.id]"
                                :invalid="invalid"
                                :disabled="readOnly" /><Select
                                v-else-if="field.type === 'select'"
                                :id="id"
                                v-model="form.custom_fields[field.id]"
                                :invalid="invalid"
                                :disabled="readOnly"
                                ><option value="">
                                    {{ $t('ui.select.placeholder') }}
                                </option>
                                <option
                                    v-for="option in field.options"
                                    :key="option"
                                    :value="option"
                                >
                                    {{ option }}
                                </option></Select
                            ><Input
                                v-else
                                :id="id"
                                v-model="form.custom_fields[field.id]"
                                :type="
                                    field.type === 'text' ? 'text' : field.type
                                "
                                :invalid="invalid"
                                :disabled="readOnly"
                        /></FormField></div
                ></Card>
                <Card
                    ><div class="flex items-start justify-between gap-3">
                        <div>
                            <h2 class="m-0 text-xl font-bold text-muted">
                                {{ $t('credentials.assignments.title') }}
                            </h2>
                            <p class="mt-1 mb-4 text-xs text-muted">
                                {{ $t('credentials.assignments.lead') }}
                            </p>
                        </div>
                        <Button
                            v-if="!readOnly"
                            type="button"
                            size="sm"
                            @click="addPass"
                            ><Icon
                                :name="['fas', 'plus']"
                                class="mr-1.5"
                                size="sm"
                            />{{
                                $t('credentials.assignments.actions.give')
                            }}</Button
                        >
                    </div>
                    <div class="space-y-2">
                        <div
                            v-for="(assignment, index) in form.pass_assignments"
                            :key="assignment.id ?? `new-${index}`"
                            class="grid items-center gap-2 rounded-lg border border-line p-3 sm:grid-cols-[1fr_1fr_auto]"
                        >
                            <CustomDropdown
                                v-model="assignment.pass_type_id"
                                :items="passItems"
                                :disabled="readOnly"
                            />
                            <CustomDropdown
                                v-model="assignment.person_id"
                                :items="contactItems"
                                :disabled="readOnly"
                            />
                            <IconButton
                                v-if="!readOnly"
                                :icon="['fas', 'circle-minus']"
                                :label="
                                    $t('credentials.assignments.actions.remove')
                                "
                                tone="delete"
                                @click="form.pass_assignments.splice(index, 1)"
                            />
                        </div>
                        <p
                            v-if="!form.pass_assignments.length"
                            class="m-0 py-3 text-sm text-muted"
                        >
                            {{ $t('credentials.assignments.empty') }}
                        </p>
                    </div></Card
                >
                <Card
                    ><h2 class="m-0 text-xl font-bold text-muted">
                        {{ $t('vendors.contracts_phase') }}
                    </h2>
                    <div
                        class="mt-3 rounded-lg border border-dashed border-line bg-page p-5 text-center text-sm text-muted"
                    >
                        {{ $t('vendors.contracts_phase') }}
                    </div></Card
                >
                <Card
                    ><div class="flex items-start justify-between gap-3">
                        <div>
                            <h2 class="m-0 text-xl font-bold text-muted">
                                {{ $t('vendors.note_log') }}
                            </h2>
                            <p class="mt-1 mb-4 text-xs text-muted">
                                {{ $t('vendors.note_log_hint') }}
                            </p>
                        </div>
                        <Button
                            v-if="!readOnly && !composing"
                            type="button"
                            size="sm"
                            @click="addNote"
                            ><Icon
                                :name="['fas', 'plus']"
                                class="mr-1.5"
                                size="sm"
                            />{{ $t('vendors.new_note') }}</Button
                        >
                    </div>
                    <div
                        v-if="composing"
                        class="mb-3 rounded-lg border border-primary bg-primary/10 p-3"
                    >
                        <Textarea
                            v-model="draftNote"
                            data-note-compose
                            :placeholder="$t('vendors.note_placeholder')"
                        />
                        <div class="mt-2 flex justify-end gap-2">
                            <Button
                                type="button"
                                size="sm"
                                variant="cancel"
                                @click="
                                    composing = false;
                                    draftNote = '';
                                "
                                >{{ $t('setup.actions.cancel') }}</Button
                            ><Button
                                type="button"
                                size="sm"
                                @click="stageNote"
                                >{{ $t('vendors.post_note') }}</Button
                            >
                        </div>
                    </div>
                    <div class="space-y-2">
                        <div
                            v-for="(note, index) in allNotes"
                            :key="note.id"
                            class="rounded-lg border border-line bg-page p-3"
                        >
                            <div class="flex justify-between gap-3 text-xs">
                                <strong>{{
                                    note.author ||
                                    $t('vendors.notes_author_unknown')
                                }}</strong
                                ><span class="text-muted">{{
                                    noteTime(note.created_at)
                                }}</span>
                            </div>
                            <p class="mt-1 mb-0 text-sm whitespace-pre-wrap">
                                {{ note.body }}
                            </p>
                            <Button
                                v-if="
                                    String(note.id).startsWith('draft-') &&
                                    !readOnly
                                "
                                type="button"
                                size="sm"
                                variant="ghost"
                                class="mt-1"
                                @click="form.notes.splice(index, 1)"
                                >{{ $t('people.actions.remove') }}</Button
                            >
                        </div>
                        <p
                            v-if="!allNotes.length"
                            class="m-0 py-3 text-sm text-muted"
                        >
                            {{ $t('vendors.notes_empty') }}
                        </p>
                    </div></Card
                >
                <div
                    v-if="!readOnly"
                    class="fixed right-0 bottom-0 left-0 z-30 border-t border-line bg-ground/95 py-3 backdrop-blur lg:left-56"
                >
                    <div class="container mx-auto px-4 md:px-6">
                        <div
                            class="mx-auto flex max-w-6xl items-center justify-between"
                        >
                            <Button
                                href="/vendors/advancing"
                                variant="cancel"
                                :disabled="form.processing"
                            >
                                {{ $t('setup.actions.cancel') }}
                            </Button>
                            <Button
                                type="submit"
                                :loading="form.processing"
                            >
                                {{ $t('vendors.save_details') }}
                            </Button>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
