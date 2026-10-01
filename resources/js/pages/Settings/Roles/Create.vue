<script setup>
import RoleForm from './RoleForm.vue';
defineProps({
    role: { type: Object, default: null },
    permissionGroups: { type: Object, required: true },
});
</script>
<template>
    <RoleForm
        :role="role"
        :permission-groups="permissionGroups"
    />
</template>
