<script setup>
import EntitlementLabels from './EntitlementLabels.vue';

defineProps({
    name: { type: String, required: true },
    labels: { type: Array, default: () => [] },
    stacked: { type: Boolean, default: false },
});
</script>

<template>
    <span
        class="flex max-w-full min-w-0 flex-col gap-1.5"
        :class="!stacked && 'md:flex-row md:flex-wrap md:items-center'"
    >
        <span class="min-w-0 font-semibold [overflow-wrap:anywhere]">{{
            name
        }}</span>
        <EntitlementLabels
            :labels="labels"
            :item-name="name"
            class="w-full"
            :class="!stacked && 'md:w-auto'"
        />
    </span>
</template>
