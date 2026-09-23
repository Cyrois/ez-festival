<script setup>
import { Badge } from '../ui/badge';
import { Button } from '../ui/button';

defineProps({
    entitlements: { type: Array, required: true },
    canWrite: { type: Boolean, default: true },
});

defineEmits(['details', 'consume']);
</script>

<template>
    <div class="space-y-2">
        <div
            v-for="entitlement in entitlements"
            :key="entitlement.id"
            class="flex flex-wrap items-center gap-3 rounded-xl border border-line bg-page px-4 py-3"
        >
            <strong class="min-w-0 flex-1 text-sm">{{
                entitlement.name
            }}</strong>
            <Badge
                :variant="
                    entitlement.status === 'issued' ? 'success' : 'warning'
                "
                pill
                >{{
                    $t(
                        `artists.check_in.entitlement_status.${entitlement.status}`,
                    )
                }}</Badge
            >
            <Button
                variant="ghost"
                size="sm"
                @click="$emit('details', entitlement)"
                >{{ $t('artists.check_in.details') }}</Button
            >
            <Button
                v-if="entitlement.status === 'pending'"
                size="sm"
                :disabled="!canWrite || entitlement.locations.length === 0"
                @click="$emit('consume', entitlement)"
                >{{ $t('artists.check_in.consume') }}</Button
            >
            <Button
                v-else
                variant="outline"
                size="sm"
                disabled
                >{{ $t('artists.check_in.remove') }}</Button
            >
        </div>
    </div>
</template>
