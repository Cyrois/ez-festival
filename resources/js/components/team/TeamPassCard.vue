<script setup>
import { Badge } from '../ui/badge';
import { Icon } from '../ui/icon';
import { IconButton } from '../ui/icon-button';
import { Tag } from '../ui/tag';
import { trans } from 'laravel-vue-i18n';
import { computed } from 'vue';

const props = defineProps({
    assignment: { type: Object, required: true },
    pass: { type: Object, required: true },
    canWrite: { type: Boolean, required: true },
});

defineEmits(['remove']);

const status = computed(() => {
    if (props.assignment.issue_state === 'issued') {
        return {
            label: trans('team.member.passes.claim_state.claimed'),
            variant: 'success',
            icon: ['fas', 'check'],
        };
    }

    if (props.assignment.issue_state === 'partially_issued') {
        return {
            label: trans('team.member.passes.claim_state.partially_claimed'),
            variant: 'warning',
            icon: null,
        };
    }

    return {
        label: trans('team.member.passes.claim_state.not_claimed'),
        variant: 'neutral',
        icon: null,
    };
});
</script>

<template>
    <article
        class="flex flex-wrap items-center gap-3 rounded-lg border border-line px-3 py-3"
    >
        <p class="m-0 text-sm font-semibold text-charcoal">
            {{ pass.name }}
        </p>
        <div
            v-if="pass.labels.length"
            class="flex flex-wrap gap-1.5"
        >
            <Tag
                v-for="label in pass.labels"
                :key="label.id"
                :name="label.name"
                :color="label.color"
            />
        </div>
        <Badge
            :variant="status.variant"
            pill
        >
            <Icon
                v-if="status.icon"
                :name="status.icon"
                class="mr-1"
                size="sm"
            />
            {{ status.label }}
        </Badge>
        <IconButton
            v-if="canWrite"
            :icon="['fas', 'circle-minus']"
            :label="$t('team.member.passes.actions.remove')"
            tone="delete"
            class="ml-auto"
            :disabled="!assignment.can_remove"
            @click="$emit('remove')"
        />
    </article>
</template>
