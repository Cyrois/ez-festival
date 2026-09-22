<script setup>
import AppLayout from '../../layouts/AppLayout.vue';
import { Button } from '../../components/ui/button';
import { Card } from '../../components/ui/card';
import { FormField } from '../../components/ui/form-field';
import { Input } from '../../components/ui/input';
import { Tag } from '../../components/ui/tag';
import { useFlashToast } from '../../composables/useFlashToast';
import { useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import { trans } from 'laravel-vue-i18n';

const props = defineProps({
    event: { type: Object, required: true },
    item: { type: Object, required: true },
    labels: { type: Array, default: () => [] },
});

const form = useForm({
    name: props.item.name,
    label_ids: props.item.labels.map((label) => label.id),
    new_labels: [],
});
const { showFormError } = useFlashToast();
const breadcrumbs = computed(() => [
    { label: trans('nav.credentials'), href: '/credentials/passes' },
    {
        label: trans('nav.credentials.entitlements'),
        href: '/credentials/entitlements',
    },
    { label: trans('credentials.entitlements.edit.title') },
]);

const toggleLabel = (id) => {
    form.label_ids = form.label_ids.includes(id)
        ? form.label_ids.filter((value) => value !== id)
        : [...form.label_ids, id];
};

const submit = () => {
    form.put(
        `/events/${props.event.id}/credentials/entitlements/${props.item.id}`,
        { onError: showFormError },
    );
};
</script>

<template>
    <AppLayout
        :title="$t('credentials.entitlements.edit.title')"
        :breadcrumbs="breadcrumbs"
    >
        <div class="container mx-auto max-w-3xl">
            <div class="mb-7">
                <h1 class="m-0 text-2xl font-bold tracking-tight">
                    {{ $t('credentials.entitlements.edit.title') }}
                </h1>
                <p class="mt-1 mb-0 text-sm text-muted">
                    {{ $t('credentials.entitlements.edit.lead') }}
                </p>
            </div>

            <form @submit.prevent="submit">
                <Card class="p-5 sm:p-6">
                    <FormField
                        :label="$t('credentials.entitlements.fields.name')"
                        :error="form.errors.name"
                        required
                    >
                        <template #default="{ id, invalid }">
                            <Input
                                :id="id"
                                v-model="form.name"
                                :invalid="invalid"
                                autocomplete="off"
                            />
                        </template>
                    </FormField>

                    <div class="mt-5">
                        <p class="m-0 text-xs font-bold text-charcoal">
                            {{ $t('credentials.entitlements.labels.title') }}
                        </p>
                        <div class="mt-2 flex flex-wrap gap-2">
                            <button
                                v-for="label in labels"
                                :key="label.id"
                                type="button"
                                class="rounded-full focus-visible:ring-[3px] focus-visible:ring-primary/35 focus-visible:outline-none"
                                :class="
                                    form.label_ids.includes(label.id)
                                        ? 'ring-2 ring-primary ring-offset-2'
                                        : ''
                                "
                                @click="toggleLabel(label.id)"
                            >
                                <Tag
                                    :name="label.name"
                                    :color="label.color"
                                />
                            </button>
                        </div>
                    </div>

                    <div
                        class="mt-5 flex justify-end gap-2 border-t border-line pt-5"
                    >
                        <Button
                            href="/credentials/entitlements"
                            variant="ghost"
                            :disabled="form.processing"
                        >
                            {{ $t('setup.actions.cancel') }}
                        </Button>
                        <Button
                            type="submit"
                            :loading="form.processing"
                        >
                            {{ $t('credentials.entitlements.actions.save') }}
                        </Button>
                    </div>
                </Card>
            </form>
        </div>
    </AppLayout>
</template>
