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
    <div class="hidden shrink-0 border-t border-line p-2 lg:block">
        <Button
            variant="ghost"
            size="sm"
            class="w-full overflow-hidden text-muted transition-[padding,gap,color,background-color] duration-200 ease-in-out hover:text-charcoal"
            :class="
                compact
                    ? 'justify-center gap-0 px-0'
                    : 'justify-start gap-2 px-3'
            "
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
            />
            <span
                class="overflow-hidden whitespace-nowrap transition-[max-width,opacity] duration-150 ease-out"
                :class="compact ? 'max-w-0 opacity-0' : 'max-w-48 opacity-100'"
            >
                {{
                    collapsed
                        ? $t('nav.expand_sidebar')
                        : $t('nav.collapse_sidebar')
                }}
            </span>
        </Button>
    </div>
</template>
