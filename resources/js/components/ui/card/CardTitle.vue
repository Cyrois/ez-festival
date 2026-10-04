<script setup>
defineProps({
    as: {
        type: String,
        default: 'h2',
        validator: (value) => ['h2', 'h3', 'h4'].includes(value),
    },
});
</script>

<template>
    <component
        :is="as"
        class="m-0 text-xl font-bold text-muted"
    >
        <slot />
    </component>
</template>
