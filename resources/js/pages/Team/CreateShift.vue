<script setup>
import ShiftEditor from '../../components/team/ShiftEditor.vue';

const props = defineProps({
    event: { type: Object, required: true },
    prefill: { type: Object, default: () => ({}) },
    locations: { type: Array, required: true },
    labelColors: { type: Array, required: true },
    roles: { type: Array, default: () => [] },
    canManage: { type: Boolean, default: false },
    breakOptions: { type: Object, required: true },
    mealOptions: { type: Array, default: () => [] },
    canConfigureMeals: { type: Boolean, default: false },
    returnContext: { type: Object, default: () => ({}) },
});
</script>

<template>
    <ShiftEditor v-bind="props" />
</template>
