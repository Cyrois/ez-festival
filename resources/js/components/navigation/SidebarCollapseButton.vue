<script setup>
import { Icon } from '../ui/icon';

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
        <button
            type="button"
            class="inline-flex h-9 w-full items-center gap-2 rounded-lg text-[13px] font-semibold text-muted transition-colors hover:bg-page hover:text-charcoal focus-visible:ring-[3px] focus-visible:ring-primary/35 focus-visible:outline-none"
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
        </button>
    </div>
</template>
