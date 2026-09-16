<script setup>
import AppLayout from '../../layouts/AppLayout.vue';
import { Button } from '../../components/ui/button';
import { Card } from '../../components/ui/card';
import { Checkbox } from '../../components/ui/checkbox';
import { FormField } from '../../components/ui/form-field';
import { Icon } from '../../components/ui/icon';
import { Input } from '../../components/ui/input';
import { Select } from '../../components/ui/select';
import { Tag } from '../../components/ui/tag';
import { useFlashToast } from '../../composables/useFlashToast';
import { useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import { trans } from 'laravel-vue-i18n';

const props = defineProps({
    event: { type: Object, required: true },
    types: { type: Array, required: true },
    labels: { type: Array, required: true },
    statuses: { type: Array, required: true },
    labelColors: { type: Array, required: true },
});
const form = useForm({
    name: '',
    status: 'idea',
    artist_type_id: '',
    label_ids: [],
    new_labels: [],
});
const { showFormError } = useFlashToast();
const breadcrumbs = computed(() => [
    { label: trans('nav.artists'), href: '/artists/advancing' },
    { label: trans('artists.add') },
]);
const toggleLabel = (id) => {
    form.label_ids = form.label_ids.includes(id)
        ? form.label_ids.filter((value) => value !== id)
        : [...form.label_ids, id];
};
const submit = () =>
    form.post(`/events/${props.event.id}/artists`, { onError: showFormError });
</script>

<template>
    <AppLayout
        :title="$t('artists.add')"
        :breadcrumbs="breadcrumbs"
    >
        <div class="max-w-2xl">
            <div class="mb-6">
                <h1 class="m-0 text-2xl font-bold tracking-tight">
                    {{ $t('artists.add') }}
                </h1>
                <p class="mt-1 mb-0 text-sm text-muted">
                    {{ $t('artists.create_lead', { name: event.name }) }}
                </p>
            </div>
            <Card>
                <form
                    class="space-y-5"
                    @submit.prevent="submit"
                >
                    <FormField
                        v-slot="{ id, invalid }"
                        :label="$t('artists.name')"
                        :error="form.errors.name"
                        :hint="$t('artists.name_hint')"
                        required
                    >
                        <Input
                            :id="id"
                            v-model="form.name"
                            :invalid="invalid"
                            maxlength="255"
                            required
                            autofocus
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
                                :disabled="form.processing"
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
                                :disabled="form.processing"
                                @click="form.new_labels.splice(index, 1)"
                            >
                                <Icon :name="['fas', 'trash']" />
                            </Button>
                        </div>
                        <Button
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
                    <div
                        class="flex justify-end gap-2 border-t border-line pt-5"
                    >
                        <Button
                            href="/artists/advancing"
                            variant="ghost"
                            :disabled="form.processing"
                            >{{ $t('setup.actions.cancel') }}</Button
                        >
                        <Button
                            type="submit"
                            :loading="form.processing"
                            >{{ $t('artists.save') }}</Button
                        >
                    </div>
                </form>
            </Card>
        </div>
    </AppLayout>
</template>
