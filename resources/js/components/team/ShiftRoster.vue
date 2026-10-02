<script setup>
import { computed } from 'vue';
import { Button } from '../ui/button';
import { Badge } from '../ui/badge';
import { Icon } from '../ui/icon';
import ShiftOverlapWarnings from './ShiftOverlapWarnings.vue';
import { requirementRoster } from '../../lib/shiftAssignments';
const props = defineProps({
    shift: { type: Object, required: true },
    enabled: { type: Boolean, default: false },
    canRemove: { type: Boolean, default: false },
    disabledReason: { type: String, default: '' },
    busy: { type: Boolean, default: false },
});
defineEmits(['assign', 'remove']);
const detached = computed(() =>
    props.shift.assignments.filter(
        (assignment) => assignment.shift_role_slot_id === null,
    ),
);
</script>

<template>
    <section>
        <h2 class="m-0 mb-2 text-xl font-bold text-muted">
            {{ $t('team.scheduling.assignments.roster') }}
        </h2>
        <p class="m-0 text-sm text-muted">
            {{
                $t('team.scheduling.slots.filled', {
                    filled: shift.filled_count,
                    count: shift.total_needs,
                })
            }}
        </p>
        <p
            v-if="shift.extra_count"
            class="mt-1 text-sm text-warning"
        >
            {{
                $t(
                    shift.extra_count === 1
                        ? 'team.scheduling.assignments.extra_one'
                        : 'team.scheduling.assignments.extra_many',
                    { count: shift.extra_count },
                )
            }}
        </p>
        <p
            v-if="shift.total_needs && shift.filled_count === shift.total_needs"
            class="mt-2 text-sm text-muted"
        >
            {{ $t('team.scheduling.assignments.full') }}
        </p>
        <p
            v-if="disabledReason"
            class="mt-2 text-sm text-muted"
        >
            {{ disabledReason }}
        </p>
        <div
            v-for="slot in shift.slots"
            :key="slot.id"
            class="mt-5 border-t border-line pt-4"
        >
            <div
                class="grid gap-3 sm:grid-cols-[minmax(0,1fr)_12rem_12rem_7rem]"
            >
                <h3 class="m-0 text-base font-bold">{{ slot.role_name }}</h3>
                <span class="hidden text-sm font-bold text-muted sm:block">{{
                    $t('team.scheduling.fields.start')
                }}</span>
                <span class="hidden text-sm font-bold text-muted sm:block">{{
                    $t('team.scheduling.fields.end')
                }}</span>
            </div>
            <p
                v-if="slot.extra_count"
                class="mt-2 text-sm text-warning"
            >
                {{
                    $t('team.scheduling.assignments.over_qty', {
                        assigned: slot.assigned_count,
                        needed: slot.needed,
                    })
                }}
            </p>
            <ul class="m-0 mt-2 list-none p-0">
                <li
                    v-for="assignment in requirementRoster(shift, slot)"
                    :key="assignment.id"
                    class="grid items-start gap-3 border-b border-line py-3 last:border-b-0 sm:grid-cols-[minmax(0,1fr)_12rem_12rem_7rem]"
                >
                    <div>
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="font-semibold">{{
                                assignment.name
                            }}</span
                            ><Badge
                                v-if="assignment.is_extra"
                                pill
                                variant="warning"
                                >{{
                                    $t('team.scheduling.assignments.extra')
                                }}</Badge
                            >
                        </div>
                        <p class="m-0 mt-1 text-sm text-muted">
                            {{ assignment.role_name }}
                        </p>
                        <ShiftOverlapWarnings :overlaps="assignment.overlaps" />
                    </div>
                    <span class="text-sm text-muted">{{
                        assignment.starts_at.replace('T', ' ')
                    }}</span>
                    <span class="text-sm text-muted">{{
                        assignment.ends_at.replace('T', ' ')
                    }}</span>
                    <Button
                        v-if="canRemove"
                        type="button"
                        variant="ghost"
                        :disabled="!enabled || busy"
                        :title="disabledReason"
                        :aria-label="
                            $t('team.scheduling.assignments.reassign_person', {
                                name: assignment.name,
                            })
                        "
                        @click="$emit('remove', assignment)"
                        >{{
                            $t('team.scheduling.assignments.reassign')
                        }}</Button
                    >
                </li>
                <li
                    v-for="position in slot.open_count"
                    :key="`open-${position}`"
                    class="grid items-center gap-3 border-b border-line py-3 text-sm text-muted last:border-b-0 sm:grid-cols-[minmax(0,1fr)_12rem_12rem_7rem]"
                >
                    <span>{{
                        $t('team.scheduling.assignments.open_position')
                    }}</span>
                    <span>{{ shift.starts_at.replace('T', ' ') }}</span>
                    <span>{{ shift.ends_at.replace('T', ' ') }}</span>
                    <Button
                        type="button"
                        variant="ghost"
                        :disabled="!enabled || busy"
                        :title="disabledReason"
                        @click="$emit('assign', slot)"
                        ><Icon
                            :name="['fas', 'plus']"
                            size="sm"
                        />{{ $t('team.scheduling.assignments.assign') }}</Button
                    >
                </li>
            </ul>
        </div>
        <div
            v-if="detached.length"
            class="mt-5 border-t border-line pt-4"
        >
            <h3 class="m-0 text-base font-bold">
                {{ $t('team.scheduling.assignments.detached') }}
            </h3>
            <ul class="m-0 list-none p-0">
                <li
                    v-for="assignment in detached"
                    :key="assignment.id"
                    class="grid items-start gap-3 border-b border-line py-3 last:border-b-0 sm:grid-cols-[minmax(0,1fr)_12rem_12rem_7rem]"
                >
                    <div>
                        <p class="m-0 font-semibold">
                            {{ assignment.name }}
                            <Badge
                                pill
                                variant="warning"
                                >{{
                                    $t('team.scheduling.assignments.extra')
                                }}</Badge
                            >
                        </p>
                        <p class="m-0 mt-1 text-sm text-muted">
                            {{ assignment.role_name }}
                        </p>
                        <ShiftOverlapWarnings :overlaps="assignment.overlaps" />
                    </div>
                    <span class="text-sm text-muted">{{
                        assignment.starts_at.replace('T', ' ')
                    }}</span>
                    <span class="text-sm text-muted">{{
                        assignment.ends_at.replace('T', ' ')
                    }}</span>
                    <Button
                        v-if="canRemove"
                        type="button"
                        variant="ghost"
                        :disabled="!enabled || busy"
                        :aria-label="
                            $t('team.scheduling.assignments.reassign_person', {
                                name: assignment.name,
                            })
                        "
                        @click="$emit('remove', assignment)"
                        >{{
                            $t('team.scheduling.assignments.reassign')
                        }}</Button
                    >
                </li>
            </ul>
        </div>
        <p
            v-if="!shift.slots.length && !detached.length"
            class="mt-4 text-sm text-muted"
        >
            {{ $t('team.scheduling.assignments.no_requirements') }}
        </p>
    </section>
</template>
