<script setup>
import EventEditShell from '../../../components/settings/EventEditShell.vue';

defineProps({
    event: {
        type: Object,
        required: true,
    },
    tab: {
        type: String,
        default: 'roles',
    },
});
</script>

<template>
    <EventEditShell
        :event="event"
        :tab="tab"
    />
</template>
