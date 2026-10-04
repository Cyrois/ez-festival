<script setup>
import { CardTitle } from '../ui/card';
import { computed, ref } from 'vue';
import { Button } from '../ui/button';
import { CustomDropdown } from '../ui/custom-dropdown';
import { FormField } from '../ui/form-field';
import { QuantityInput } from '../ui/quantity-input';
import { newShiftSlot, totalShiftNeeds } from '../../lib/shiftRoleSlots';

const props = defineProps({
    modelValue: { type: Array, required: true },
    roles: { type: Array, default: () => [] },
    errors: { type: Object, default: () => ({}) },
    assignmentCounts: { type: Object, default: () => ({}) },
    editable: { type: Boolean, default: true },
    busy: { type: Boolean, default: false },
    title: { type: String, required: true },
    disabledReason: { type: String, default: '' },
});
const emit = defineEmits(['update:modelValue', 'clear-error']);
const pending = ref(newShiftSlot());
const pendingErrors = ref({});
const ordered = computed(() => props.modelValue);
const total = computed(() => totalShiftNeeds(props.modelValue));
const roleItems = computed(() =>
    props.roles.map((role) => ({
        value: role.id,
        title: role.name,
    })),
);
const assignedCount = (slot) =>
    props.assignmentCounts[slot.id ?? slot._key] ?? slot.assigned_count ?? 0;
const error = (slot, field) => props.errors[slot._key]?.[field] ?? '';
const update = (slot, field, value) => {
    if (!props.editable || props.busy) return;
    const changes = {
        [field]:
            field === 'needed' && value !== '' && Number.isFinite(Number(value))
                ? Math.max(1, assignedCount(slot), Number(value))
                : value,
    };
    emit(
        'update:modelValue',
        props.modelValue.map((row) =>
            row._key === slot._key ? { ...row, ...changes } : row,
        ),
    );
    emit('clear-error', slot._key, field);
};
const add = () => {
    if (!props.editable || props.busy) return;
    pendingErrors.value = {};
    if (!props.roles.some((role) => role.id === pending.value.role_id)) {
        pendingErrors.value.role_id =
            'team.scheduling.slots.errors.role_required';
    }
    const qty = Number(pending.value.needed);
    if (!Number.isInteger(qty) || qty < 1 || qty > 2147483647) {
        pendingErrors.value.needed = 'team.scheduling.slots.errors.needed';
    }
    if (Object.keys(pendingErrors.value).length) return;
    emit('update:modelValue', [
        ...props.modelValue,
        {
            ...pending.value,
            needed: qty,
            role_name: props.roles.find(
                (role) => role.id === pending.value.role_id,
            ).name,
        },
    ]);
    pending.value = newShiftSlot();
};
const remove = (slot) => {
    if (props.editable && !props.busy && assignedCount(slot) === 0) {
        emit(
            'update:modelValue',
            props.modelValue.filter((row) => row._key !== slot._key),
        );
    }
};
</script>

<template>
    <section class="space-y-4">
        <div class="flex items-center justify-between gap-3">
            <CardTitle>{{ title }}</CardTitle>
            <p class="m-0 shrink-0 text-sm text-muted">
                {{ $t('team.scheduling.slots.total', { count: total }) }}
            </p>
        </div>
        <div
            v-if="editable"
            class="grid gap-3 rounded-lg border border-line bg-page p-3 sm:grid-cols-[minmax(0,1fr)_10rem_auto]"
        >
            <FormField
                :label="$t('team.scheduling.slots.role')"
                :error="pendingErrors.role_id ? $t(pendingErrors.role_id) : ''"
                required
            >
                <template #default="{ id, invalid }">
                    <CustomDropdown
                        :id="id"
                        v-model="pending.role_id"
                        :items="roleItems"
                        :placeholder="$t('team.scheduling.slots.pick_role')"
                        :empty-text="$t('team.scheduling.slots.no_roles')"
                        :invalid="invalid"
                        :disabled="!editable || busy"
                        @update:model-value="delete pendingErrors.role_id"
                    />
                </template>
            </FormField>
            <FormField
                :label="$t('team.scheduling.slots.qty')"
                :error="pendingErrors.needed ? $t(pendingErrors.needed) : ''"
                required
            >
                <template #default="{ id, invalid }">
                    <QuantityInput
                        :id="id"
                        v-model="pending.needed"
                        :label="$t('team.scheduling.slots.qty')"
                        :invalid="invalid"
                        :disabled="!editable || busy"
                        @update:model-value="delete pendingErrors.needed"
                    />
                </template>
            </FormField>
            <Button
                type="button"
                class="h-10 border-0 sm:self-end"
                :disabled="!editable || busy"
                @click="add"
                >{{ $t('team.scheduling.slots.add') }}</Button
            >
        </div>
        <Button
            v-if="!editable"
            type="button"
            disabled
            :title="disabledReason"
            >{{ $t('team.scheduling.slots.add') }}</Button
        >
        <div
            v-for="slot in ordered"
            :key="slot._key"
            :data-headcount-row="slot._key"
            class="space-y-2 rounded-lg border border-line p-3"
        >
            <div
                v-if="editable"
                class="grid items-start gap-2 sm:grid-cols-[minmax(0,1fr)_auto]"
            >
                <div class="pt-2">
                    <p class="m-0 text-sm font-semibold">
                        {{ slot.role_name }}
                    </p>
                    <p
                        v-if="error(slot, 'role_id')"
                        class="mt-1 text-xs text-danger"
                        role="alert"
                    >
                        {{ error(slot, 'role_id') }}
                    </p>
                </div>
                <FormField :error="error(slot, 'needed')">
                    <template #default="{ id, invalid }">
                        <QuantityInput
                            :id="id"
                            :label="$t('team.scheduling.slots.qty')"
                            :model-value="slot.needed"
                            :min="Math.max(1, assignedCount(slot))"
                            remove-at-one
                            :remove-disabled="assignedCount(slot) > 0"
                            :remove-label="
                                $t('team.scheduling.slots.remove', {
                                    role:
                                        slot.role_name ||
                                        $t('team.scheduling.slots.role'),
                                })
                            "
                            :disabled-reason="
                                $t(
                                    Number(slot.needed) === 1
                                        ? 'team.scheduling.slots.assigned_slot_tooltip'
                                        : 'team.scheduling.slots.errors.assigned_qty',
                                    { count: assignedCount(slot) },
                                )
                            "
                            :invalid="invalid"
                            :disabled="busy"
                            @remove="remove(slot)"
                            @update:model-value="update(slot, 'needed', $event)"
                        />
                    </template>
                </FormField>
            </div>
            <div
                v-else
                class="flex items-center justify-between gap-3 border-b border-line py-2 text-sm"
            >
                <span>{{ slot.role_name }}</span>
                <span>{{
                    $t('team.scheduling.slots.count', { count: slot.needed })
                }}</span>
            </div>
            <p
                v-if="slot.id && assignedCount(slot) > Number(slot.needed)"
                class="mt-1 text-sm text-warning"
                role="status"
            >
                {{
                    $t('team.scheduling.assignments.over_qty', {
                        assigned: assignedCount(slot),
                        needed: slot.needed,
                    })
                }}
            </p>
            <p
                v-if="error(slot, 'id')"
                class="m-0 text-xs text-danger"
                role="alert"
            >
                {{ error(slot, 'id') }}
            </p>
        </div>
        <p
            v-if="!modelValue.length"
            class="m-0 text-sm text-muted"
        >
            {{ $t('team.scheduling.slots.empty') }}
        </p>
    </section>
</template>
