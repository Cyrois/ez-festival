<script setup>
import { Link } from '@inertiajs/vue3';
import { Avatar } from '../ui/avatar';
import { Tag } from '../ui/tag';

defineProps({
    engagement: { type: Object, required: true },
});
</script>

<template>
    <article class="rounded-lg border border-line bg-ground p-3 shadow-sm">
        <div class="flex min-w-0 items-center gap-2.5">
            <Avatar
                :name="engagement.name"
                size="sm"
            />
            <div class="min-w-0">
                <Link
                    :href="`/artists/engagements/${engagement.id}`"
                    class="block truncate font-semibold text-charcoal no-underline hover:text-primary hover:underline"
                >
                    {{ engagement.name }}
                </Link>
                <p class="mt-0.5 mb-0 truncate text-xs text-muted">
                    {{ engagement.type || $t('artists.not_set') }}
                </p>
            </div>
        </div>
        <div
            v-if="engagement.labels.length"
            class="mt-2 flex flex-wrap gap-1"
        >
            <Tag
                v-for="label in engagement.labels"
                :key="label.id"
                :name="label.name"
                :color="label.color"
            />
        </div>
    </article>
</template>
