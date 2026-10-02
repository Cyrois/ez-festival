<script setup>
import { computed, nextTick } from 'vue';
import { Button } from '../ui/button';
import { Checkbox } from '../ui/checkbox';
import { CustomDropdown } from '../ui/custom-dropdown';
import { FormField } from '../ui/form-field';
import { Icon } from '../ui/icon';
import { IconButton } from '../ui/icon-button';
import { Input } from '../ui/input';
import {
    newShiftSlot,
    orderedShiftSlots,
    totalShiftNeeds,
} from '../../lib/shiftRoleSlots';

const props = defineProps({
    modelValue: { type: Array, required: true },
    roles: { type: Array, default: () => [] },
    errors: { type: Object, default: () => ({}) },
    editable: { type: Boolean, default: true },
    busy: { type: Boolean, default: false },
    title: { type: String, required: true },
    disabledReason: { type: String, default: '' },
});
const emit = defineEmits(['update:modelValue', 'clear-error']);
const ordered = computed(() => orderedShiftSlots(props.modelValue));
const total = computed(() => totalShiftNeeds(props.modelValue));
const roleItems = computed(() =>
    props.roles.map((role) => ({
        value: role.id,
        title: role.name,
    })),
);
const error = (slot, field) => props.errors[slot._key]?.[field] ?? '';
const update = (slot, field, value) => {
    if (!props.editable || props.busy) return;
    const focused = document.activeElement;
    const changes = { [field]: value };
    if (field === 'role_id') {
        changes.role_name =
            props.roles.find((role) => role.id === value)?.name ?? '';
    }
    emit(
        'update:modelValue',
        props.modelValue.map((row) =>
            row._key === slot._key ? { ...row, ...changes } : row,
        ),
    );
    emit('clear-error', slot._key, field);
    if (field === 'is_supervisor') {
        nextTick(() => focused?.isConnected && focused.focus());
    }
};
const add = () => {
    if (props.editable && !props.busy) {
        emit('update:modelValue', [...props.modelValue, newShiftSlot()]);
    }
};
const remove = (slot) => {
    if (props.editable && !props.busy) {
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
            <h2 class="m-0 text-base font-bold text-charcoal">{{ title }}</h2>
            <p class="m-0 shrink-0 text-sm text-muted">
                {{ $t('team.scheduling.slots.total', { count: total }) }}
            </p>
        </div>
        <div
            v-for="slot in ordered"
            :key="slot._key"
            class="space-y-2"
        >
            <div
                v-if="editable"
                class="grid grid-cols-[minmax(0,1fr)_5rem_2.75rem] items-start gap-2"
            >
                <FormField
                    :label="$t('team.scheduling.slots.role')"
                    :error="error(slot, 'role_id')"
                    required
                >
                    <template #default="{ id, invalid }">
                        <CustomDropdown
                            :id="id"
                            :model-value="slot.role_id"
                            :items="roleItems"
                            :placeholder="
                                slot.role_name ||
                                $t('team.scheduling.slots.pick_role')
                            "
                            :empty-text="$t('team.scheduling.slots.no_roles')"
                            :invalid="invalid"
                            :disabled="busy"
                            @update:model-value="
                                update(slot, 'role_id', $event)
                            "
                        />
                    </template>
                </FormField>
                <FormField
                    :label="$t('team.scheduling.slots.needed')"
                    :error="error(slot, 'needed')"
                    required
                >
                    <template #default="{ id, invalid }">
                        <Input
                            :id="id"
                            :model-value="slot.needed"
                            type="number"
                            min="1"
                            max="2147483647"
                            step="1"
                            :invalid="invalid"
                            :disabled="busy"
                            @update:model-value="update(slot, 'needed', $event)"
                        />
                    </template>
                </FormField>
                <IconButton
                    :icon="['fas', 'trash-can']"
                    tone="delete"
                    class="mt-6 h-10 w-10"
                    :disabled="busy"
                    :label="
                        $t('team.scheduling.slots.remove', {
                            role:
                                slot.role_name ||
                                $t('team.scheduling.slots.role'),
                        })
                    "
                    @click="remove(slot)"
                />
            </div>
            <Checkbox
                v-if="editable"
                :model-value="slot.is_supervisor"
                :label="$t('team.scheduling.slots.supervisor')"
                :invalid="Boolean(error(slot, 'is_supervisor'))"
                :disabled="busy"
                @update:model-value="update(slot, 'is_supervisor', $event)"
            />
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
                v-if="error(slot, 'id') || error(slot, 'is_supervisor')"
                class="m-0 text-xs text-danger"
                role="alert"
            >
                {{ error(slot, 'id') || error(slot, 'is_supervisor') }}
            </p>
        </div>
        <p
            v-if="!modelValue.length"
            class="m-0 text-sm text-muted"
        >
            {{ $t('team.scheduling.slots.empty') }}
        </p>
        <span
            class="inline-block"
            :title="!editable ? disabledReason : undefined"
            :tabindex="!editable ? 0 : undefined"
        >
            <Button
                type="button"
                variant="ghost"
                class="text-secondary"
                :disabled="!editable || busy"
                @click="add"
            >
                <Icon
                    :name="['fas', 'plus']"
                    class="mr-1.5"
                />
                {{ $t('team.scheduling.slots.add') }}
            </Button>
        </span>
    </section>
</template>
