<script setup>
import AdvancementBoardCard from '../advancement/AdvancementBoardCard.vue';
import { Tag } from '../ui/tag';

defineProps({
    engagement: { type: Object, required: true },
});
</script>

<template>
    <AdvancementBoardCard
        :name="engagement.name"
        :subtitle="engagement.type || $t('artists.not_set')"
        :href="`/artists/engagements/${engagement.id}`"
    >
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
    </AdvancementBoardCard>
</template>
