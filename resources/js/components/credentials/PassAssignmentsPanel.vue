<script setup>
import { Button } from '../ui/button';
import { CustomDropdown } from '../ui/custom-dropdown';
import { FormField } from '../ui/form-field';
import { Input } from '../ui/input';
import { useFlashToast } from '../../composables/useFlashToast';
import { router, useForm } from '@inertiajs/vue3';
import { trans } from 'laravel-vue-i18n';
import { computed } from 'vue';

const props = defineProps({
    assignments: { type: Array, default: () => [] },
    people: { type: Array, default: () => [] },
    passes: { type: Array, default: () => [] },
    basePath: { type: String, required: true },
    canWrite: { type: Boolean, required: true },
});

const form = useForm({ pass_id: '', quantity: 1 });
const { showFormError } = useFlashToast();

const capacityLabel = (pass) =>
    pass.max_assignments === null
        ? trans('credentials.assignments.unlimited')
        : trans('credentials.assignments.remaining', {
              count: Math.max(0, pass.max_assignments - pass.assignments_count),
          });

const passItems = computed(() =>
    props.passes.map((pass) => ({
        value: pass.id,
        title: pass.name,
        description: capacityLabel(pass),
        disabled:
            pass.max_assignments !== null &&
            pass.assignments_count >= pass.max_assignments,
    })),
);

const personItems = computed(() => [
    {
        value: '',
        title: trans('credentials.assignments.actions.assign'),
    },
    ...props.people.map((person) => ({
        value: person.id,
        title: person.name,
    })),
]);

const give = () => {
    form.post(`${props.basePath}/pass-assignments`, {
        preserveScroll: true,
        onError: showFormError,
        onSuccess: () => form.reset('pass_id', 'quantity'),
    });
};

const assign = (assignment, personId) => {
    if (!personId) return;
    router.put(
        `/pass-assignments/${assignment.id}`,
        { person_id: personId },
        { preserveScroll: true },
    );
};

const remove = (assignment) => {
    router.delete(`/pass-assignments/${assignment.id}`, {
        preserveScroll: true,
    });
};
</script>

<template>
    <section>
        <h2 class="m-0 text-lg font-semibold text-charcoal">
            {{ $t('credentials.assignments.title') }}
        </h2>
        <p class="mt-1 mb-0 text-xs text-muted">
            {{ $t('credentials.assignments.lead') }}
        </p>

        <form
            v-if="canWrite"
            class="mt-4 grid gap-3 rounded-lg border border-line bg-page p-3 sm:grid-cols-[1fr_8rem_auto]"
            @submit.prevent="give"
        >
            <FormField
                v-slot="{ id, invalid }"
                :label="$t('credentials.assignments.fields.pass')"
                :error="form.errors.pass_id"
                required
            >
                <CustomDropdown
                    :id="id"
                    v-model="form.pass_id"
                    :invalid="invalid"
                    :items="passItems"
                    :placeholder="$t('ui.select.placeholder')"
                />
            </FormField>
            <FormField
                v-slot="{ id, invalid }"
                :label="$t('credentials.assignments.fields.quantity')"
                :error="form.errors.quantity"
                required
            >
                <Input
                    :id="id"
                    v-model="form.quantity"
                    :invalid="invalid"
                    type="number"
                    min="1"
                    max="100"
                    required
                />
            </FormField>
            <div class="flex items-end">
                <Button
                    type="submit"
                    :loading="form.processing"
                >
                    {{ $t('credentials.assignments.actions.give') }}
                </Button>
            </div>
        </form>

        <div class="mt-4 space-y-2">
            <div
                v-for="assignment in assignments"
                :key="assignment.id"
                class="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-line px-3 py-2"
            >
                <div>
                    <p class="m-0 text-sm font-semibold">
                        {{ assignment.pass_name }}
                    </p>
                    <p class="mt-0.5 mb-0 text-xs text-muted">
                        {{
                            assignment.person
                                ? $t('credentials.assignments.assigned_to', {
                                      name: assignment.person.name,
                                  })
                                : $t('credentials.assignments.unassigned')
                        }}
                    </p>
                </div>
                <div
                    v-if="canWrite"
                    class="flex items-center gap-2"
                >
                    <CustomDropdown
                        class="w-44"
                        :model-value="assignment.person?.id ?? ''"
                        :items="personItems"
                        @update:model-value="assign(assignment, $event)"
                    />
                    <Button
                        size="sm"
                        variant="ghost"
                        @click="remove(assignment)"
                    >
                        {{ $t('credentials.assignments.actions.remove') }}
                    </Button>
                </div>
            </div>
            <p
                v-if="assignments.length === 0"
                class="m-0 py-3 text-sm text-muted"
            >
                {{ $t('credentials.assignments.empty') }}
            </p>
        </div>
    </section>
</template>
