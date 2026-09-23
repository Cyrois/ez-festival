<script setup>
import { Dialog } from '../ui/dialog';
import { Badge } from '../ui/badge';

const props = defineProps({
    open: { type: Boolean, default: false },
    entitlement: { type: Object, default: null },
    timezone: { type: String, default: 'America/Vancouver' },
});

defineEmits(['update:open']);

const formatWhen = (value) => {
    if (!value) {
        return null;
    }

    try {
        return new Intl.DateTimeFormat('en-US', {
            dateStyle: 'medium',
            timeStyle: 'short',
            timeZone: props.timezone || 'America/Vancouver',
        }).format(new Date(value));
    } catch {
        return value;
    }
};
</script>

<template>
    <Dialog
        :open="open"
        :title="$t('artists.check_in.details_title')"
        :cancel-label="$t('artists.check_in.done')"
        :show-confirm="false"
        @update:open="$emit('update:open', $event)"
    >
        <dl
            v-if="entitlement"
            class="mt-4 grid grid-cols-[8rem_1fr] gap-x-4 gap-y-3 text-sm"
        >
            <dt class="font-semibold text-muted">
                {{ $t('artists.check_in.item') }}
            </dt>
            <dd class="m-0 font-bold">{{ entitlement.name }}</dd>
            <dt class="font-semibold text-muted">
                {{ $t('artists.check_in.status_label') }}
            </dt>
            <dd class="m-0">
                <Badge
                    :variant="
                        entitlement.status === 'issued' ? 'success' : 'warning'
                    "
                    pill
                >
                    {{
                        $t(
                            `artists.check_in.entitlement_status.${entitlement.status}`,
                        )
                    }}
                </Badge>
            </dd>
            <dt class="font-semibold text-muted">
                {{ $t('artists.check_in.quantity') }}
            </dt>
            <dd class="m-0">1</dd>
            <dt class="font-semibold text-muted">
                {{ $t('artists.check_in.from_location') }}
            </dt>
            <dd class="m-0">
                {{
                    entitlement.issued?.location ||
                    $t('artists.check_in.not_set')
                }}
            </dd>
            <dt class="font-semibold text-muted">
                {{ $t('artists.check_in.code') }}
            </dt>
            <dd class="m-0 font-mono text-xs">
                {{ entitlement.issued?.code || $t('artists.check_in.not_set') }}
            </dd>
            <dt class="font-semibold text-muted">
                {{ $t('artists.check_in.issued_at') }}
            </dt>
            <dd class="m-0">
                {{
                    formatWhen(entitlement.issued?.issued_at) ||
                    $t('artists.check_in.not_set')
                }}
            </dd>
            <dt class="font-semibold text-muted">
                {{ $t('artists.check_in.source') }}
            </dt>
            <dd class="m-0">{{ entitlement.source }}</dd>
        </dl>
    </Dialog>
</template>
