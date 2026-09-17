<script setup>
import AppLayout from '../../layouts/AppLayout.vue';
import { Avatar } from '../../components/ui/avatar';
import { Button } from '../../components/ui/button';
import { Card } from '../../components/ui/card';
import { FormField } from '../../components/ui/form-field';
import { Icon } from '../../components/ui/icon';
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
    canWrite: { type: Boolean, required: true },
});
const form = useForm({
    name: props.engagement.name,
    status: props.engagement.status,
    vendor_type_id: props.engagement.vendor_type_id ?? '',
});
const noteForm = useForm({ body: '' });
const composing = ref(false);
const { showError, showFormError } = useFlashToast();
const readOnly = computed(() => !props.canWrite);
const breadcrumbs = computed(() => [
    { label: trans('app.name'), href: '/dashboard' },
    { label: trans('nav.vendors'), href: '/vendors/advancing' },
    { label: trans('vendors.view') },
]);
const submit = () => {
    if (!readOnly.value) {
        form.put(`/vendors/engagements/${props.engagement.id}`, {
            onError: (errors) =>
                toastFormErrors(form, errors, { showError, showFormError }),
        });
    }
};
const openCompose = async () => {
    if (readOnly.value) return;
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
    if (readOnly.value) return;
    noteForm.post(`/vendors/engagements/${props.engagement.id}/notes`, {
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
    if (!iso) return '';
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
        back-href="/vendors/advancing"
        :back-label="$t('vendors.back_to_advancing')"
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
            {{ $t('vendors.view_lead', { name: event.name }) }}
        </p>
        <p
            v-if="readOnly"
            class="mb-4 flex items-center gap-2 rounded-lg border border-warning/20 bg-warning/10 p-3 text-sm text-charcoal"
            role="status"
        >
            <Icon :name="['fas', 'lock']" />
            {{ $t('vendors.view_locked') }}
        </p>
        <div class="grid items-start gap-4.5 lg:grid-cols-2">
            <Card class="flex min-h-[32rem] flex-col">
                <h2
                    class="mt-0 mb-1 text-xl font-bold tracking-tight text-muted"
                >
                    {{ $t('vendors.details') }}
                </h2>
                <p class="mt-0 mb-3.5 text-xs text-muted">
                    {{ $t('vendors.details_hint') }}
                </p>
                <form
                    class="flex flex-1 flex-col space-y-5"
                    @submit.prevent="submit"
                >
                    <FormField
                        v-slot="{ id, invalid }"
                        :label="$t('vendors.name')"
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
                            :label="$t('vendors.columns.status')"
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
                                    {{ $t(`vendors.status.${status}`) }}
                                </option>
                            </Select>
                        </FormField>
                        <FormField
                            v-slot="{ id, invalid }"
                            :label="$t('vendors.columns.type')"
                            :error="form.errors.vendor_type_id"
                        >
                            <Select
                                :id="id"
                                v-model="form.vendor_type_id"
                                :invalid="invalid"
                                :disabled="readOnly || form.processing"
                            >
                                <option value="">
                                    {{ $t('vendors.type_optional') }}
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
                    <p
                        class="m-0 text-[11px] font-bold tracking-wider text-muted uppercase"
                    >
                        {{ $t('vendors.contracts_phase') }}
                    </p>
                    <div
                        class="mt-auto flex justify-end gap-2.5 border-t border-line pt-4"
                    >
                        <Button
                            href="/vendors/advancing"
                            variant="outline"
                            :disabled="form.processing"
                            >{{ $t('setup.actions.cancel') }}</Button
                        >
                        <Button
                            v-if="!readOnly"
                            type="submit"
                            :loading="form.processing"
                            >{{ $t('vendors.save_details') }}</Button
                        >
                    </div>
                </form>
            </Card>
            <Card class="flex min-h-[32rem] flex-col">
                <div class="mb-1 flex items-start justify-between gap-3">
                    <h2 class="m-0 text-xl font-bold tracking-tight text-muted">
                        {{ $t('vendors.note_log') }}
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
                        />{{ $t('vendors.new_note') }}
                    </Button>
                </div>
                <p class="mt-0 mb-3.5 text-xs text-muted">
                    {{ $t('vendors.note_log_hint') }}
                </p>
                <div class="flex min-h-0 flex-1 flex-col">
                    <div
                        v-if="composing"
                        class="mb-3 rounded-[10px] border border-primary bg-primary/10 p-3"
                    >
                        <FormField
                            v-slot="{ id, invalid }"
                            :label="$t('vendors.new_note')"
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
                                        $t('vendors.note_placeholder')
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
                                        >{{ $t('vendors.post_note') }}</Button
                                    >
                                    <Button
                                        size="sm"
                                        variant="outline"
                                        class="w-full sm:w-auto"
                                        :disabled="noteForm.processing"
                                        @click="cancelCompose"
                                        >{{
                                            $t('setup.actions.cancel')
                                        }}</Button
                                    >
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
                                    note.author ||
                                    $t('vendors.notes_author_unknown')
                                }}</strong
                                ><span class="text-muted">{{
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
                            {{ $t('vendors.notes_empty') }}
                        </p>
                    </div>
                </div>
            </Card>
        </div>
    </AppLayout>
</template>
