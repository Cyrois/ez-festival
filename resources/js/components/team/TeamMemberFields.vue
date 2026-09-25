<script setup>
import { CustomDropdown } from '../ui/custom-dropdown';
import { FormField } from '../ui/form-field';
import { Input } from '../ui/input';
import { computed } from 'vue';
import { trans } from 'laravel-vue-i18n';

const props = defineProps({
    form: { type: Object, required: true },
    groups: { type: Array, required: true },
    statuses: { type: Array, required: true },
    employmentTypes: { type: Array, required: true },
    disabled: { type: Boolean, default: false },
});

const emit = defineEmits(['update']);
const statusItems = computed(() =>
    props.statuses.map((status) => ({
        value: status,
        title: trans(`team.advancement.status.${status}`),
    })),
);
const employmentTypeItems = computed(() =>
    props.employmentTypes.map((type) => ({
        value: type,
        title: trans(`team.advancement.employment_type.${type}`),
    })),
);
const groupItems = computed(() => [
    { value: '', title: trans('team.member.fields.group_none') },
    ...props.groups.map((group) => ({
        value: group.id,
        title: group.name,
    })),
]);
</script>

<template>
    <div class="grid gap-4 sm:grid-cols-2">
        <FormField
            v-slot="{ id, invalid }"
            :label="$t('team.member.fields.name')"
            :error="form.errors.name"
            required
        >
            <Input
                :id="id"
                :model-value="form.name"
                :invalid="invalid"
                :disabled="disabled"
                maxlength="255"
                required
                @update:model-value="emit('update', 'name', $event)"
            />
        </FormField>
        <FormField
            v-slot="{ id, invalid }"
            :label="$t('team.member.fields.status')"
            :error="form.errors.status"
        >
            <CustomDropdown
                :id="id"
                :model-value="form.status"
                :items="statusItems"
                :invalid="invalid"
                :disabled="disabled"
                @update:model-value="emit('update', 'status', $event)"
            />
        </FormField>
        <FormField
            v-slot="{ id, invalid }"
            :label="$t('team.member.fields.employment_type')"
            :error="form.errors.employment_type"
        >
            <CustomDropdown
                :id="id"
                :model-value="form.employment_type"
                :items="employmentTypeItems"
                :invalid="invalid"
                :disabled="disabled"
                @update:model-value="emit('update', 'employment_type', $event)"
            />
        </FormField>
        <FormField
            v-if="form.employment_type === 'paid'"
            v-slot="{ id, invalid }"
            :label="$t('team.member.fields.hourly_pay')"
            :error="form.errors.hourly_pay"
            required
        >
            <Input
                :id="id"
                :model-value="form.hourly_pay"
                type="number"
                min="0"
                max="99999999.99"
                step="0.01"
                :invalid="invalid"
                :disabled="disabled"
                required
                @update:model-value="emit('update', 'hourly_pay', $event)"
            />
        </FormField>
        <FormField
            v-slot="{ id, invalid }"
            :label="$t('team.member.fields.phone')"
            :error="form.errors.phone"
        >
            <Input
                :id="id"
                :model-value="form.phone"
                type="tel"
                :invalid="invalid"
                :disabled="disabled"
                maxlength="50"
                @update:model-value="emit('update', 'phone', $event)"
            />
        </FormField>
        <FormField
            v-slot="{ id, invalid }"
            :label="$t('team.member.fields.email')"
            :error="form.errors.email"
        >
            <Input
                :id="id"
                :model-value="form.email"
                type="email"
                :invalid="invalid"
                :disabled="disabled"
                maxlength="255"
                @update:model-value="emit('update', 'email', $event)"
            />
        </FormField>
        <FormField
            v-slot="{ id, invalid }"
            :label="$t('team.member.fields.group')"
            :error="form.errors.group_id"
            :hint="$t('team.member.fields.group_hint')"
            class="sm:col-span-2"
        >
            <CustomDropdown
                :id="id"
                :model-value="form.group_id"
                :items="groupItems"
                :invalid="invalid"
                :disabled="disabled"
                @update:model-value="emit('update', 'group_id', $event)"
            />
        </FormField>
    </div>
</template>
