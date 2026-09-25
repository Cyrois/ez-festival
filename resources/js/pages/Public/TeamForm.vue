<script setup>
import { Button } from '../../components/ui/button';
import { Card } from '../../components/ui/card';
import { CustomDropdown } from '../../components/ui/custom-dropdown';
import { FormField } from '../../components/ui/form-field';
import { Input } from '../../components/ui/input';
import { Textarea } from '../../components/ui/textarea';
import { toastFormErrors } from '../../lib/fieldError';
import { useFlashToast } from '../../composables/useFlashToast';
import { Head, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import { trans } from 'laravel-vue-i18n';

const props = defineProps({
    form: { type: Object, required: true },
    event: { type: Object, required: true },
    preview: { type: Boolean, default: false },
});

const initial = { custom_fields: {} };
for (const field of props.form.fields) {
    if (field.key.startsWith('custom_fields.')) {
        initial.custom_fields[field.key.split('.')[1]] = '';
    } else {
        initial[field.key] = '';
    }
}
const application = useForm(initial);
const { showError, showFormError } = useFlashToast();

const valueFor = (field) => {
    if (!field.key.startsWith('custom_fields.')) return application[field.key];
    return application.custom_fields[field.key.split('.')[1]];
};
const updateValue = (field, value) => {
    if (!field.key.startsWith('custom_fields.')) {
        application[field.key] = value;
        return;
    }
    application.custom_fields[field.key.split('.')[1]] = value;
};
const optionsFor = (field) =>
    field.options.map((value) => ({
        value,
        title:
            field.key === 'employment_type'
                ? trans(`team.advancement.employment_type.${value}`)
                : value,
    }));
const errorFor = (field) => application.errors[field.key] ?? '';
const title = computed(() => `${props.form.name} · ${props.event.name}`);
const submit = () => {
    if (props.preview) return;

    application.post(props.form.action, {
        onError: (errors) =>
            toastFormErrors(application, errors, { showError, showFormError }),
    });
};
</script>

<template>
    <Head :title="title" />
    <main
        class="min-h-screen bg-page px-4 py-10 text-charcoal antialiased sm:px-6"
    >
        <div class="mx-auto flex w-full max-w-2xl flex-col gap-6">
            <div
                v-if="preview"
                class="rounded-lg border border-warning/40 bg-warning/10 px-4 py-3 text-center text-sm font-medium text-charcoal"
            >
                {{ $t('team.forms.public.preview_notice') }}
            </div>
            <header class="text-center">
                <div
                    class="mx-auto mb-4 inline-flex h-10 w-10 items-center justify-center rounded-lg bg-primary text-sm font-bold text-white"
                >
                    {{ $t('app.mark') }}
                </div>
                <p class="m-0 text-sm font-semibold text-primary">
                    {{ event.name }}
                </p>
                <h1 class="mt-2 mb-0 text-3xl font-bold tracking-tight">
                    {{ form.name }}
                </h1>
                <p class="mt-2 mb-0 text-sm text-muted">
                    {{ $t('team.forms.public.lead') }}
                </p>
            </header>
            <Card>
                <form
                    class="flex flex-col gap-5"
                    @submit.prevent="submit"
                >
                    <FormField
                        v-for="field in form.fields"
                        :key="field.key"
                        :label="field.label"
                        :required="field.required"
                        :error="errorFor(field)"
                    >
                        <template #default="{ id, invalid }">
                            <Textarea
                                v-if="field.type === 'textarea'"
                                :id="id"
                                :model-value="valueFor(field)"
                                :invalid="invalid"
                                @update:model-value="updateValue(field, $event)"
                            />
                            <CustomDropdown
                                v-else-if="field.type === 'select'"
                                :id="id"
                                :model-value="valueFor(field)"
                                :items="optionsFor(field)"
                                :placeholder="$t('team.forms.public.choose')"
                                :invalid="invalid"
                                @update:model-value="updateValue(field, $event)"
                            />
                            <Input
                                v-else
                                :id="id"
                                :model-value="valueFor(field)"
                                :type="
                                    field.type === 'phone' ? 'tel' : field.type
                                "
                                :autocomplete="field.key"
                                :invalid="invalid"
                                @update:model-value="updateValue(field, $event)"
                            />
                        </template>
                    </FormField>
                    <Button
                        v-if="!preview"
                        type="submit"
                        size="lg"
                        class="mt-2 w-full"
                        :loading="application.processing"
                    >
                        {{ $t('team.forms.public.submit') }}
                    </Button>
                </form>
            </Card>
            <p class="m-0 text-center text-xs text-muted">
                {{
                    preview
                        ? $t('team.forms.public.preview_foot')
                        : $t('team.forms.public.foot')
                }}
            </p>
        </div>
    </main>
</template>
