<script setup>
import ShiftEditor from '../../components/team/ShiftEditor.vue';
import CopyShiftEditor from '../../components/team/CopyShiftEditor.vue';

const props = defineProps({
    event: { type: Object, required: true },
    prefill: { type: Object, default: () => ({}) },
    locations: { type: Array, required: true },
    labelColors: { type: Array, required: true },
    roles: { type: Array, default: () => [] },
    canManage: { type: Boolean, default: false },
    breakOptions: { type: Object, required: true },
    returnContext: { type: Object, default: () => ({}) },
});
</script>

<template>
    <CopyShiftEditor
        v-if="prefill.copy"
        v-bind="props"
    />
    <ShiftEditor
        v-else
        v-bind="props"
    />
</template>
