<script setup>
import { Badge } from '../ui/badge';
import { Button } from '../ui/button';
import { CustomDropdown } from '../ui/custom-dropdown';
import { Icon } from '../ui/icon';
import { IconButton } from '../ui/icon-button';
import { Tag } from '../ui/tag';
import { trans } from 'laravel-vue-i18n';
import { computed } from 'vue';

const props = defineProps({
    passes: { type: Array, default: () => [] },
    canWrite: { type: Boolean, required: true },
    hired: { type: Boolean, required: true },
    errors: { type: Object, default: () => ({}) },
});

const assignments = defineModel('assignments', {
    type: Array,
    required: true,
});
const canMutate = computed(() => props.canWrite && props.hired);
const initialPassTypeCounts = new Map();

for (const assignment of assignments.value) {
    initialPassTypeCounts.set(
        assignment.pass_type_id,
        (initialPassTypeCounts.get(assignment.pass_type_id) ?? 0) + 1,
    );
}

const stagedCount = (passId, ignoredAssignment) =>
    assignments.value.filter(
        (assignment) =>
            assignment !== ignoredAssignment &&
            assignment.pass_type_id === passId,
    ).length;

const capacityFor = (pass, assignment) => {
    if (pass.max_assignments === null) {
        return { remaining: null, disabled: false };
    }

    const existingElsewhere = Math.max(
        0,
        pass.assignments_count - (initialPassTypeCounts.get(pass.id) ?? 0),
    );

    const slotsBeforeSelection =
        pass.max_assignments -
        existingElsewhere -
        stagedCount(pass.id, assignment);

    return {
        remaining: Math.max(0, slotsBeforeSelection - 1),
        disabled:
            slotsBeforeSelection <= 0 && assignment.pass_type_id !== pass.id,
    };
};

const passItemsFor = (assignment) =>
    props.passes.map((pass) => {
        const capacity = capacityFor(pass, assignment);

        return {
            value: pass.id,
            title: pass.name,
            description:
                capacity.remaining === null
                    ? trans('team.member.passes.capacity.unlimited')
                    : trans('team.member.passes.capacity.remaining', {
                          count: capacity.remaining,
                      }),
            disabled: capacity.disabled,
        };
    });

const selectedPass = (assignment) =>
    props.passes.find((pass) => pass.id === assignment.pass_type_id);

const addPass = () => {
    assignments.value.push({
        id: null,
        pass_type_id: '',
        issue_state: 'unissued',
        can_remove: true,
    });
};

const removePass = (index) => {
    assignments.value.splice(index, 1);
};

const issueLabel = (state) => trans(`team.member.passes.issue_state.${state}`);
const assignmentError = (index) =>
    props.errors[`pass_assignments.${index}.pass_type_id`] ?? '';
</script>

<template>
    <section>
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h2 class="m-0 text-xl font-bold text-muted">
                    {{ $t('team.member.passes.title') }}
                </h2>
                <p class="mt-1 mb-0 text-xs text-muted">
                    {{ $t('team.member.passes.description') }}
                </p>
            </div>
            <Button
                v-if="canMutate"
                type="button"
                size="sm"
                :disabled="passes.length === 0"
                @click="addPass"
            >
                <Icon
                    :name="['fas', 'plus']"
                    class="mr-1.5"
                    size="sm"
                />
                {{ $t('team.member.passes.actions.give') }}
            </Button>
        </div>

        <p
            v-if="!hired"
            class="mt-4 rounded-lg border border-dashed border-line bg-page p-5 text-center text-sm text-muted"
        >
            {{ $t('team.member.passes.hired_required') }}
        </p>

        <div class="mt-4 space-y-2">
            <div
                v-for="(assignment, index) in assignments"
                :key="assignment.id ?? `new-${index}`"
                class="flex flex-col items-start gap-3 rounded-lg border border-line p-3 sm:flex-row"
            >
                <div class="w-full min-w-0 sm:w-1/2">
                    <CustomDropdown
                        v-model="assignment.pass_type_id"
                        :items="passItemsFor(assignment)"
                        :invalid="Boolean(assignmentError(index))"
                        :disabled="
                            !canMutate ||
                            assignment.id !== null ||
                            assignment.issue_state !== 'unissued'
                        "
                        :placeholder="$t('ui.select.placeholder')"
                        :empty-text="$t('team.member.passes.no_options')"
                    />
                    <p
                        v-if="assignmentError(index)"
                        class="mt-1 mb-0 text-xs text-danger"
                    >
                        {{ assignmentError(index) }}
                    </p>
                    <div
                        v-if="selectedPass(assignment)?.labels?.length"
                        class="mt-2 flex flex-wrap gap-1.5"
                    >
                        <Tag
                            v-for="label in selectedPass(assignment).labels"
                            :key="label.id"
                            :name="label.name"
                            :color="label.color"
                        />
                    </div>
                    <div class="mt-2 flex flex-wrap items-center gap-2">
                        <Badge
                            v-if="assignment.issue_state !== 'unissued'"
                            :variant="
                                assignment.issue_state === 'issued'
                                    ? 'success'
                                    : 'warning'
                            "
                            pill
                        >
                            {{ issueLabel(assignment.issue_state) }}
                        </Badge>
                        <span
                            v-if="!assignment.can_remove"
                            class="text-xs text-muted"
                        >
                            {{ $t('team.member.passes.remove_issued_reason') }}
                        </span>
                    </div>
                </div>
                <IconButton
                    v-if="canMutate"
                    :icon="['fas', 'circle-minus']"
                    :label="$t('team.member.passes.actions.remove')"
                    tone="delete"
                    class="ml-auto h-10 w-10"
                    :disabled="!assignment.can_remove"
                    @click="removePass(index)"
                />
            </div>
            <p
                v-if="assignments.length === 0"
                class="m-0 py-3 text-sm text-muted"
            >
                {{ $t('team.member.passes.empty') }}
            </p>
        </div>
    </section>
</template>
