<script setup>
import { Avatar } from '../ui/avatar';
import { Badge } from '../ui/badge';

defineProps({
    people: { type: Array, required: true },
    selectedId: { type: [Number, String], default: null },
});

defineEmits(['select']);

const status = (person) => {
    if (person.expected === 0 || person.issued >= person.expected)
        return 'complete';
    if (person.issued === 0) return 'not_started';
    return 'partial';
};
</script>

<template>
    <aside class="space-y-2">
        <p class="m-0 text-xs font-bold tracking-wide text-muted uppercase">
            {{ $t('artists.check_in.people') }}
        </p>
        <button
            v-for="person in people"
            :key="person.id"
            type="button"
            class="flex w-full gap-3 rounded-xl border p-3 text-left transition-colors"
            :class="
                person.id === selectedId
                    ? 'border-primary bg-primary-soft'
                    : 'border-line bg-ground hover:border-primary/50'
            "
            @click="$emit('select', person.id)"
        >
            <Avatar
                :name="person.name"
                size="sm"
            />
            <span class="min-w-0 flex-1">
                <span class="flex items-start justify-between gap-2">
                    <span class="truncate text-sm font-bold">{{
                        person.name
                    }}</span>
                    <Badge
                        :variant="
                            status(person) === 'partial' ? 'warning' : 'neutral'
                        "
                        pill
                        >{{
                            $t(`artists.check_in.status.${status(person)}`)
                        }}</Badge
                    >
                </span>
                <span class="mt-0.5 block truncate text-xs text-muted">
                    {{
                        person.is_primary
                            ? $t('artists.check_in.primary_contact')
                            : $t('artists.check_in.contact')
                    }}
                </span>
                <span class="mt-1 flex justify-between gap-2 text-xs">
                    <span class="truncate text-secondary">{{
                        person.passes.join(', ')
                    }}</span>
                    <strong>{{ person.issued }}/{{ person.expected }}</strong>
                </span>
            </span>
        </button>
    </aside>
</template>
