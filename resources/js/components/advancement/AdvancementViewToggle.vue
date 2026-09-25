<script setup>
import { SegmentedControl } from '../ui/segmented-control';
import { computed } from 'vue';
import { trans } from 'laravel-vue-i18n';

defineProps({
    modelValue: { type: String, required: true },
});

const emit = defineEmits(['update:modelValue']);
const options = computed(() => [
    {
        value: 'columns',
        label: trans('advancement.views.columns'),
        icon: ['fas', 'table-columns'],
    },
    {
        value: 'list',
        label: trans('advancement.views.list'),
        icon: ['fas', 'list'],
    },
]);
</script>

<template>
    <SegmentedControl
        :model-value="modelValue"
        :options="options"
        :aria-label="$t('advancement.views.mode')"
        @update:model-value="emit('update:modelValue', $event)"
    />
</template>
