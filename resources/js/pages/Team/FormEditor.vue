<script setup>
import AppLayout from '../../layouts/AppLayout.vue';
import { Button } from '../../components/ui/button';
import { Card } from '../../components/ui/card';
import { Checkbox } from '../../components/ui/checkbox';
import { CustomDropdown } from '../../components/ui/custom-dropdown';
import { FormField } from '../../components/ui/form-field';
import { Icon } from '../../components/ui/icon';
import { Input } from '../../components/ui/input';
import { Textarea } from '../../components/ui/textarea';
import { useFlashToast } from '../../composables/useFlashToast';
import { fieldError, toastFormErrors } from '../../lib/fieldError';
import { useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import { trans } from 'laravel-vue-i18n';

const props = defineProps({
    event: { type: Object, required: true },
    teamForm: { type: Object, default: null },
    statuses: { type: Array, default: () => [] },
    initialFields: { type: Array, required: true },
});

const editing = computed(() => props.teamForm !== null);
const breadcrumbs = computed(() => [
    { label: trans('app.name'), href: '/dashboard' },
    { label: trans('team.title'), href: '/team/advancement' },
    { label: trans('nav.team.forms'), href: '/team/forms' },
    {
        label: trans(
            editing.value
                ? 'team.forms.editor.edit_crumb'
                : 'team.forms.editor.create_crumb',
        ),
    },
]);
const statusItems = computed(() =>
    props.statuses.map((value) => ({
        value,
        title: trans(`team.forms.status.${value}`),
    })),
);
const typeItems = computed(() =>
    ['text', 'textarea', 'select'].map((value) => ({
        value,
        title: trans(`team.forms.field_type.${value}`),
    })),
);
const { showError, showFormError, showSuccess } = useFlashToast();
const initialFormFields = (props.teamForm?.fields ?? props.initialFields).map(
    (field) => ({
        ...field,
        required: ['name', 'email'].includes(field.key) ? true : field.required,
        options: [...(field.options ?? [])],
    }),
);
const form = useForm({
    name: props.teamForm?.name ?? '',
    slug: props.teamForm?.slug ?? '',
    status: props.teamForm?.status ?? 'draft',
    fields: initialFormFields,
});
const slugEdited = ref(editing.value);
const slugPreview = computed(
    () => form.slug || trans('team.forms.editor.slug_placeholder'),
);
const fieldList = ref(null);
const draggedFieldKey = ref(null);
const activeDropIndex = ref(null);
let activePointerId = null;
let pointerStart = null;
let rowMidpoints = [];

watch(
    () => form.name,
    (name) => {
        if (slugEdited.value) return;

        form.slug = name
            .toLowerCase()
            .trim()
            .replace(/[^a-z0-9]+/g, '-')
            .replace(/^-|-$/g, '');
    },
);

const isBuiltin = (field) =>
    ['name', 'email', 'phone', 'employment_type'].includes(field.key);

const addField = () => {
    form.fields.push({
        id: null,
        key: `custom_${crypto.randomUUID()}`,
        label: '',
        type: 'text',
        required: false,
        options: [],
        optionsText: '',
    });
};

const removeField = (index) => form.fields.splice(index, 1);
const endFieldDrag = () => {
    activePointerId = null;
    pointerStart = null;
    rowMidpoints = [];
    draggedFieldKey.value = null;
    activeDropIndex.value = null;
};
const startFieldDrag = (event, key) => {
    if (event.button !== 0) return;

    activePointerId = event.pointerId;
    pointerStart = { x: event.clientX, y: event.clientY };
    rowMidpoints = Array.from(
        fieldList.value?.querySelectorAll('[data-field-index]') ?? [],
    ).map((row) => {
        const { top, height } = row.getBoundingClientRect();

        return top + height / 2;
    });
    draggedFieldKey.value = key;
    activeDropIndex.value = null;
    event.currentTarget.setPointerCapture(event.pointerId);
    event.preventDefault();
};
const updateFieldDropIndex = (event) => {
    if (event.pointerId !== activePointerId || !fieldList.value) return;
    if (
        Math.abs(event.clientX - pointerStart.x) < 4 &&
        Math.abs(event.clientY - pointerStart.y) < 4
    )
        return;

    const nextIndex = rowMidpoints.findIndex(
        (midpoint) => event.clientY < midpoint,
    );

    activeDropIndex.value = nextIndex === -1 ? rowMidpoints.length : nextIndex;
};
const dropField = (event) => {
    if (event.pointerId !== activePointerId) return;
    updateFieldDropIndex(event);
    const targetIndex = activeDropIndex.value;
    const from = form.fields.findIndex(
        (field) => field.key === draggedFieldKey.value,
    );
    if (targetIndex === null || from === -1) {
        endFieldDrag();
        return;
    }
    let to = targetIndex;
    if (from < to) to -= 1;
    if (from === to) {
        endFieldDrag();
        return;
    }
    const [moved] = form.fields.splice(from, 1);
    form.fields.splice(to, 0, moved);
    endFieldDrag();
};
const copyLink = async () => {
    await navigator.clipboard.writeText(props.teamForm.public_url);
    showSuccess(trans('team.forms.toast.link_copied'));
};
const submit = () => {
    form.transform((data) => ({
        ...data,
        fields: data.fields.map((field) => ({
            id: field.id,
            key: field.key,
            label: field.label,
            type: field.type,
            required: field.required,
            options:
                field.type === 'select'
                    ? (field.optionsText ?? field.options.join('\n'))
                          .split('\n')
                          .map((option) => option.trim())
                          .filter(Boolean)
                    : [],
        })),
    }));
    const options = {
        onError: (errors) =>
            toastFormErrors(form, errors, { showError, showFormError }),
    };
    if (editing.value) {
        form.put(`/team/forms/${props.teamForm.id}`, options);
        return;
    }
    form.post(`/team/events/${props.event.id}/forms`, options);
};
</script>

<template>
    <AppLayout
        :title="
            editing
                ? $t('team.forms.editor.edit_title')
                : $t('team.forms.editor.create_title')
        "
        :breadcrumbs="breadcrumbs"
        back-href="/team/forms"
        :back-label="$t('team.forms.actions.back')"
    >
        <form
            id="team-form-editor"
            class="container mx-auto flex flex-col gap-4 pb-24"
            @submit.prevent="submit"
        >
            <div>
                <h1 class="m-0 text-2xl font-bold tracking-tight">
                    {{
                        editing
                            ? $t('team.forms.editor.edit_title')
                            : $t('team.forms.editor.create_title')
                    }}
                </h1>
                <p class="mt-1 mb-0 text-sm text-muted">
                    {{ $t('team.forms.editor.lead') }}
                </p>
            </div>
            <Card :title="$t('team.forms.editor.form_card')">
                <div class="flex flex-col gap-4">
                    <FormField
                        :label="$t('team.forms.editor.name')"
                        :error="fieldError(form, 'name')"
                        required
                    >
                        <template #default="{ id, invalid }">
                            <Input
                                :id="id"
                                v-model="form.name"
                                :invalid="invalid"
                            />
                        </template>
                    </FormField>
                    <FormField
                        :label="$t('team.forms.editor.slug')"
                        :hint="
                            $t('team.forms.editor.slug_hint', {
                                slug: slugPreview,
                            })
                        "
                        :error="fieldError(form, 'slug')"
                        required
                    >
                        <template #default="{ id, invalid }">
                            <Input
                                :id="id"
                                v-model="form.slug"
                                :invalid="invalid"
                                :placeholder="
                                    $t('team.forms.editor.slug_placeholder')
                                "
                                @update:model-value="slugEdited = true"
                            />
                        </template>
                    </FormField>
                    <FormField
                        :label="$t('team.forms.editor.status')"
                        :error="fieldError(form, 'status')"
                        required
                    >
                        <template #default="{ id, invalid }">
                            <CustomDropdown
                                :id="id"
                                v-model="form.status"
                                :items="statusItems"
                                :invalid="invalid"
                            />
                        </template>
                    </FormField>
                    <div
                        v-if="editing && form.status === 'live'"
                        class="flex items-center gap-2 rounded-lg border border-line bg-page p-3"
                    >
                        <code class="min-w-0 flex-1 truncate text-xs">{{
                            teamForm.public_url
                        }}</code>
                        <Button
                            type="button"
                            size="sm"
                            @click="copyLink"
                        >
                            {{ $t('team.forms.actions.copy_link') }}
                        </Button>
                    </div>
                    <div
                        v-if="editing && form.status === 'draft'"
                        class="flex items-center justify-between gap-3 rounded-lg border border-line bg-page p-3"
                    >
                        <span class="text-sm text-muted">
                            {{ $t('team.forms.editor.preview_hint') }}
                        </span>
                        <Button
                            :href="teamForm.preview_url"
                            target="_blank"
                            rel="noopener noreferrer"
                            type="button"
                            variant="secondary"
                            size="sm"
                        >
                            {{ $t('team.forms.actions.preview') }}
                        </Button>
                    </div>
                </div>
            </Card>
            <Card>
                <template #header>
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <h2 class="m-0 text-lg font-semibold">
                                {{ $t('team.forms.editor.fields_card') }}
                            </h2>
                            <p class="mt-1 mb-0 text-xs text-muted">
                                {{ $t('team.forms.editor.fields_lead') }}
                            </p>
                        </div>
                        <Button
                            type="button"
                            variant="secondary"
                            size="sm"
                            @click="addField"
                        >
                            <Icon
                                :name="['fas', 'plus']"
                                size="sm"
                                class="mr-2"
                            />
                            {{ $t('team.forms.actions.add_field') }}
                        </Button>
                    </div>
                </template>
                <div
                    ref="fieldList"
                    class="flex flex-col gap-2"
                >
                    <template
                        v-for="(field, index) in form.fields"
                        :key="field.key"
                    >
                        <div
                            v-if="
                                draggedFieldKey !== null &&
                                activeDropIndex === index
                            "
                            class="flex min-h-[64px] items-center justify-center rounded-lg border-2 border-dashed border-primary bg-primary/10 text-sm font-medium text-primary"
                        >
                            {{ $t('team.forms.editor.drop_here') }}
                        </div>
                        <div
                            :data-field-index="index"
                            class="rounded-lg border border-line p-3"
                            :class="{
                                'ring-2 ring-primary/40':
                                    draggedFieldKey === field.key,
                            }"
                        >
                            <div class="flex flex-wrap items-start gap-3">
                                <div class="self-center">
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="sm"
                                        class="cursor-grab touch-none px-2 text-muted active:cursor-grabbing"
                                        :aria-label="
                                            $t('team.forms.actions.drag_field')
                                        "
                                        :disabled="form.processing"
                                        data-field-drag-handle
                                        @pointerdown="
                                            startFieldDrag($event, field.key)
                                        "
                                        @pointermove="updateFieldDropIndex"
                                        @pointerup="dropField"
                                        @pointercancel="endFieldDrag"
                                    >
                                        <Icon
                                            :name="['fas', 'grip-lines']"
                                            fixed-width
                                        />
                                    </Button>
                                </div>
                                <div
                                    class="grid min-w-64 flex-1 grid-cols-1 gap-3 md:grid-cols-2"
                                >
                                    <FormField
                                        :label="
                                            $t('team.forms.editor.field_label')
                                        "
                                    >
                                        <template #default="{ id }">
                                            <Input
                                                :id="id"
                                                v-model="field.label"
                                                :disabled="isBuiltin(field)"
                                            />
                                        </template>
                                    </FormField>
                                    <FormField
                                        :label="
                                            $t('team.forms.editor.field_type')
                                        "
                                    >
                                        <template #default="{ id }">
                                            <Input
                                                v-if="isBuiltin(field)"
                                                :id="id"
                                                :model-value="
                                                    $t(
                                                        `team.forms.field_type.${field.type}`,
                                                    )
                                                "
                                                disabled
                                            />
                                            <CustomDropdown
                                                v-else
                                                :id="id"
                                                v-model="field.type"
                                                :items="typeItems"
                                            />
                                        </template>
                                    </FormField>
                                    <FormField
                                        v-if="
                                            !isBuiltin(field) &&
                                            field.type === 'select'
                                        "
                                        class="md:col-span-2"
                                        :label="$t('team.forms.editor.options')"
                                        :hint="
                                            $t('team.forms.editor.options_hint')
                                        "
                                    >
                                        <template #default="{ id }">
                                            <Textarea
                                                :id="id"
                                                v-model="field.optionsText"
                                                :placeholder="
                                                    field.options.join('\n')
                                                "
                                            />
                                        </template>
                                    </FormField>
                                </div>
                                <div class="flex items-center gap-3 pt-7">
                                    <Checkbox
                                        v-model="field.required"
                                        :disabled="
                                            ['name', 'email'].includes(
                                                field.key,
                                            )
                                        "
                                        :label="
                                            $t('team.forms.editor.required')
                                        "
                                    />
                                    <Button
                                        v-if="!isBuiltin(field)"
                                        type="button"
                                        variant="ghost"
                                        size="sm"
                                        :aria-label="
                                            $t(
                                                'team.forms.actions.remove_field',
                                            )
                                        "
                                        @click="removeField(index)"
                                    >
                                        <Icon
                                            :name="['fas', 'trash']"
                                            size="sm"
                                        />
                                    </Button>
                                </div>
                            </div>
                        </div>
                    </template>
                    <div
                        v-if="
                            draggedFieldKey !== null &&
                            activeDropIndex === form.fields.length
                        "
                        class="flex min-h-[64px] items-center justify-center rounded-lg border-2 border-dashed border-primary bg-primary/10 text-sm font-medium text-primary"
                    >
                        {{ $t('team.forms.editor.drop_here') }}
                    </div>
                </div>
            </Card>
        </form>
        <div
            class="fixed right-0 bottom-0 left-0 z-30 border-t border-line bg-ground/95 py-3 backdrop-blur lg:left-56"
        >
            <div class="container mx-auto">
                <div class="flex items-center justify-between">
                    <Button
                        href="/team/forms"
                        variant="cancel"
                        :disabled="form.processing"
                    >
                        {{ $t('labels.cancel') }}
                    </Button>
                    <Button
                        form="team-form-editor"
                        type="submit"
                        :loading="form.processing"
                    >
                        {{ $t('team.forms.actions.save') }}
                    </Button>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
