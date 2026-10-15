<script setup>
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
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

const tabList = ref(null);
let resizeObserver;

const revealActiveTab = () => {
    const list = tabList.value?.$el;
    const active = list?.querySelector('[aria-current="page"]');
    if (!active) return;

    const bounds = list.getBoundingClientRect();
    const activeBounds = active.getBoundingClientRect();
    if (activeBounds.left < bounds.left) {
        list.scrollLeft -= bounds.left - activeBounds.left;
    } else if (activeBounds.right > bounds.right) {
        list.scrollLeft += activeBounds.right - bounds.right;
    }
};

onMounted(() => {
    revealActiveTab();
    if (typeof ResizeObserver === 'undefined') return;
    resizeObserver = new ResizeObserver(revealActiveTab);
    resizeObserver.observe(tabList.value.$el);
    for (const tab of tabList.value.$el.children) resizeObserver.observe(tab);
});
watch(activeTab, revealActiveTab, { flush: 'post' });
onUnmounted(() => resizeObserver?.disconnect());
</script>

<template>
    <Tabs
        :model-value="activeTab"
        navigation
    >
        <TabList
            ref="tabList"
            data-unsaved-navigation
            :aria-label="$t(`nav.${section.key}`)"
            class="h-full border-b-0"
        >
            <Tab
                v-for="tab in section.tabs"
                :key="tab.key"
                :value="tab.key"
                :href="tab.href || undefined"
                :disabled="!tab.enabled"
                class="mb-0 min-h-11 shrink-0 whitespace-nowrap"
            >
                {{ $t(`nav.${tab.key}`) }}
            </Tab>
        </TabList>
    </Tabs>
</template>
