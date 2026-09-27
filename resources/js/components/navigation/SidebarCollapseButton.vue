<script setup>
import { Icon } from '../ui/icon';
import { Button } from '../ui/button';

defineProps({
    collapsed: {
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
            class="w-full text-muted hover:text-charcoal"
            :class="collapsed ? 'justify-center px-0' : 'justify-start px-3'"
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
            <span :class="collapsed ? 'sr-only' : ''">
                {{
                    collapsed
                        ? $t('nav.expand_sidebar')
                        : $t('nav.collapse_sidebar')
                }}
            </span>
        </Button>
    </div>
</template>
