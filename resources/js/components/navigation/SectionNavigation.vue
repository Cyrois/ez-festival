<script setup>
import { computed } from 'vue';
import { Tab, TabList, Tabs } from '../ui/tabs';
import { isNavigationItemActive } from '../../lib/appNavigation';

const props = defineProps({
    section: { type: Object, required: true },
    url: { type: String, required: true },
});

const activeTab = computed(
    () =>
        props.section.tabs.find((tab) => isNavigationItemActive(tab, props.url))
            ?.key,
);
</script>

<template>
    <div class="mb-6">
        <p class="m-0 mb-6 text-2xl font-bold tracking-tight">
            {{ $t(`nav.${section.key}`) }}
        </p>
        <Tabs
            :model-value="activeTab"
            navigation
        >
            <TabList
                data-unsaved-navigation
                :aria-label="$t(`nav.${section.key}`)"
            >
                <Tab
                    v-for="tab in section.tabs"
                    :key="tab.key"
                    :value="tab.key"
                    :href="tab.href || undefined"
                    :disabled="!tab.enabled"
                    class="min-h-11 shrink-0 whitespace-nowrap"
                >
                    {{ $t(`nav.${tab.key}`) }}
                </Tab>
            </TabList>
        </Tabs>
    </div>
</template>
