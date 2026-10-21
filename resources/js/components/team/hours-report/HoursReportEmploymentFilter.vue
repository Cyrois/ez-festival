<script setup>
import { FormField } from '../../ui/form-field';
import { SegmentedControl } from '../../ui/segmented-control';

const value = defineModel({ type: String, default: 'all' });
defineProps({ options: { type: Array, required: true } });
</script>

<template>
    <FormField
        v-slot="{ id }"
        :label="$t('team.reports.filter.label')"
        class="w-full sm:w-auto"
    >
        <SegmentedControl
            :id="id"
            v-model="value"
            :options="options"
            :aria-label="$t('team.reports.filter.label')"
            variant="joined"
            equal
            class="w-full [&>button]:min-h-11"
        />
    </FormField>
</template>
