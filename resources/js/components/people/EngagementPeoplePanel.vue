<script setup>
import { Button } from '../ui/button';
import { Checkbox } from '../ui/checkbox';
import { FormField } from '../ui/form-field';
import { Input } from '../ui/input';
import { useFlashToast } from '../../composables/useFlashToast';
import { router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    people: { type: Array, default: () => [] },
    basePath: { type: String, required: true },
    canWrite: { type: Boolean, required: true },
});

const adding = ref(false);
const editingId = ref(null);
const form = useForm({ name: '', email: '', phone: '', is_primary: false });
const { showFormError } = useFlashToast();

const reset = () => {
    adding.value = false;
    editingId.value = null;
    form.reset();
    form.clearErrors();
};

const beginEdit = (person) => {
    adding.value = false;
    editingId.value = person.id;
    form.name = person.name;
    form.email = person.email ?? '';
    form.phone = person.phone ?? '';
    form.is_primary = person.is_primary;
    form.clearErrors();
};

const submit = () => {
    const options = {
        preserveScroll: true,
        onError: showFormError,
        onSuccess: reset,
    };

    if (editingId.value !== null) {
        form.put(`${props.basePath}/people/${editingId.value}`, options);
        return;
    }

    form.post(`${props.basePath}/people`, options);
};

const setPrimary = (person) => {
    router.put(
        `${props.basePath}/people/${person.id}`,
        {
            name: person.name,
            email: person.email,
            phone: person.phone,
            is_primary: true,
        },
        { preserveScroll: true },
    );
};

const remove = (person) => {
    router.delete(`${props.basePath}/people/${person.id}`, {
        preserveScroll: true,
    });
};
</script>

<template>
    <section class="border-t border-line pt-5">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h2 class="m-0 text-lg font-semibold text-charcoal">
                    {{ $t('people.title') }}
                </h2>
                <p class="mt-1 mb-0 text-xs text-muted">
                    {{ $t('people.lead') }}
                </p>
            </div>
            <Button
                v-if="canWrite && !adding && editingId === null"
                size="sm"
                @click="adding = true"
            >
                {{ $t('people.actions.add') }}
            </Button>
        </div>

        <form
            v-if="adding || editingId !== null"
            class="mt-4 grid gap-3 rounded-lg border border-line bg-page p-3 sm:grid-cols-2"
            @submit.prevent="submit"
        >
            <FormField
                v-slot="{ id, invalid }"
                :label="$t('people.fields.name')"
                :error="form.errors.name"
                required
            >
                <Input
                    :id="id"
                    v-model="form.name"
                    :invalid="invalid"
                    required
                />
            </FormField>
            <FormField
                v-slot="{ id, invalid }"
                :label="$t('people.fields.email')"
                :error="form.errors.email"
            >
                <Input
                    :id="id"
                    v-model="form.email"
                    :invalid="invalid"
                    type="email"
                />
            </FormField>
            <FormField
                v-slot="{ id, invalid }"
                :label="$t('people.fields.phone')"
                :error="form.errors.phone"
            >
                <Input
                    :id="id"
                    v-model="form.phone"
                    :invalid="invalid"
                />
            </FormField>
            <div class="flex items-end gap-2">
                <Checkbox
                    v-if="editingId !== null"
                    v-model="form.is_primary"
                >
                    {{ $t('people.fields.primary') }}
                </Checkbox>
                <Button
                    type="submit"
                    size="sm"
                    :loading="form.processing"
                >
                    {{ $t('people.actions.save') }}
                </Button>
                <Button
                    type="button"
                    size="sm"
                    variant="outline"
                    :disabled="form.processing"
                    @click="reset"
                >
                    {{ $t('setup.actions.cancel') }}
                </Button>
            </div>
        </form>

        <div class="mt-4 space-y-2">
            <div
                v-for="person in people"
                :key="person.id"
                class="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-line px-3 py-2"
            >
                <div>
                    <p class="m-0 text-sm font-semibold">{{ person.name }}</p>
                    <p class="mt-0.5 mb-0 text-xs text-muted">
                        {{
                            [person.email, person.phone]
                                .filter(Boolean)
                                .join(' · ')
                        }}
                    </p>
                </div>
                <div class="flex items-center gap-2">
                    <span
                        v-if="person.is_primary"
                        class="text-xs font-semibold text-primary"
                    >
                        {{ $t('people.primary') }}
                    </span>
                    <Button
                        v-if="canWrite && !person.is_primary"
                        size="sm"
                        variant="ghost"
                        @click="setPrimary(person)"
                    >
                        {{ $t('people.actions.make_primary') }}
                    </Button>
                    <Button
                        v-if="canWrite"
                        size="sm"
                        variant="ghost"
                        @click="beginEdit(person)"
                    >
                        {{ $t('people.actions.edit') }}
                    </Button>
                    <Button
                        v-if="canWrite"
                        size="sm"
                        variant="ghost"
                        @click="remove(person)"
                    >
                        {{ $t('people.actions.remove') }}
                    </Button>
                </div>
            </div>
            <p
                v-if="people.length === 0"
                class="m-0 py-3 text-sm text-muted"
            >
                {{ $t('people.empty') }}
            </p>
        </div>
    </section>
</template>
