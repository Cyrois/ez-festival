<script setup>
import { Switch } from '../ui/switch';

defineProps({
    modelValue: { type: Boolean, default: false },
    disabled: { type: Boolean, default: false },
    disableReason: { type: String, default: null },
    error: { type: String, default: null },
});

defineEmits(['update:modelValue']);
</script>

<template>
    <div>
        <Switch
            :model-value="modelValue"
            :disabled="disabled"
            @update:model-value="$emit('update:modelValue', $event)"
        >
            {{ $t('settings.team.admin.label') }}
        </Switch>
        <p
            class="mt-1.5 mb-0 text-xs"
            :class="disableReason ? 'text-warning' : 'text-muted'"
        >
            {{ disableReason || $t('settings.team.admin.hint') }}
        </p>
        <p
            v-if="error"
            class="mt-1.5 mb-0 text-sm text-danger"
            role="alert"
        >
            {{ error }}
        </p>
    </div>
</template>
