<script setup>
import EventEditShell from '../../../components/settings/EventEditShell.vue';

defineProps({
    event: {
        type: Object,
        required: true,
    },
    forPrimary: {
        type: Boolean,
        default: false,
    },
    tab: {
        type: String,
        default: 'users',
    },
});
</script>

<template>
    <EventEditShell
        :event="event"
        :tab="tab"
        :for-primary="forPrimary"
    />
</template>
