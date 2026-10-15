<script setup>
import { Icon } from '../ui/icon';
import { Button } from '../ui/button';

defineProps({
    collapsed: {
        type: Boolean,
        required: true,
    },
    compact: {
        type: Boolean,
        required: true,
    },
});

defineEmits(['toggle']);
</script>

<template>
    <div
        class="hidden shrink-0 border-t border-line p-2 lg:flex"
        :class="compact ? 'justify-center' : 'justify-start'"
    >
        <Button
            variant="ghost"
            size="sm"
            class="shrink-0 gap-0 p-0 text-muted transition-colors hover:text-charcoal lg:size-9 lg:min-h-9 xl:h-9.5 xl:min-h-9.5"
            :class="collapsed ? 'xl:w-9.5' : 'xl:w-[3em]'"
            :aria-label="
                collapsed
                    ? $t('nav.expand_sidebar')
                    : $t('nav.collapse_sidebar')
            "
            :title="
                collapsed
                    ? $t('nav.expand_sidebar')
                    : $t('nav.collapse_sidebar')
            "
            :aria-expanded="collapsed ? 'false' : 'true'"
            @click="$emit('toggle')"
        >
            <Icon
                :name="['fas', collapsed ? 'chevron-right' : 'chevron-left']"
                size="sm"
                fixed-width
                class="lg:text-sm xl:text-[15px]"
            />
        </Button>
    </div>
</template>
