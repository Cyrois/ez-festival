<script setup>
import { computed } from 'vue';
import { trans } from 'laravel-vue-i18n';
import { Card } from '../ui/card';
import { IconButton } from '../ui/icon-button';

const props = defineProps({
    name: {
        type: String,
        required: true,
    },
    usage: {
        type: String,
        required: true,
    },
});

const emit = defineEmits(['edit', 'delete']);

const editLabel = computed(() =>
    trans('credentials.passes.edit', { pass: props.name }),
);
const deleteLabel = computed(() =>
    trans('credentials.passes.delete', { pass: props.name }),
);
</script>

<template>
    <Card class="min-h-[60px] rounded-xl px-3.5 py-2.5">
        <div class="flex items-center justify-between gap-4">
            <div class="min-w-0">
                <h2 class="m-0 truncate text-sm font-semibold">
                    {{ name }}
                </h2>
                <p class="mt-0.5 mb-0 text-xs text-muted">
                    {{ usage }}
                </p>
            </div>
            <div class="flex shrink-0 items-center gap-2">
                <IconButton
                    :icon="['fas', 'pen']"
                    :label="editLabel"
                    tone="edit"
                    @click="emit('edit')"
                />
                <IconButton
                    :icon="['fas', 'trash-can']"
                    :label="deleteLabel"
                    tone="delete"
                    @click="emit('delete')"
                />
            </div>
        </div>
    </Card>
</template>
