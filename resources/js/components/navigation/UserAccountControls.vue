<script setup>
import { Icon } from '../ui/icon';
import { buttonVariants } from '../ui/button';
import { Link } from '@inertiajs/vue3';
import { cn } from '../../lib/utils';

const props = defineProps({
    user: {
        type: Object,
        default: null,
    },
    collapsed: {
        type: Boolean,
        default: false,
    },
});

const iconSignOutClass = cn(
    buttonVariants({ variant: 'outline', size: 'icon' }),
    'hidden text-muted no-underline hover:text-charcoal lg:inline-flex',
);
</script>

<template>
    <div class="shrink-0 space-y-1 border-t border-line px-3 py-3">
        <div
            class="min-w-0 px-2"
            :class="collapsed ? 'lg:hidden' : ''"
        >
            <p class="m-0 truncate text-xs font-semibold text-charcoal">
                {{ user?.name || user?.email || $t('dashboard.unknown') }}
            </p>
            <p
                v-if="user?.email && user?.name"
                class="m-0 truncate text-[11px] text-muted"
            >
                {{ user.email }}
            </p>
        </div>
        <Link
            method="post"
            href="/logout"
            as="button"
            class="inline-flex min-h-11 w-full items-center justify-start px-2 text-[13px] font-semibold text-primary no-underline hover:underline"
            :class="collapsed ? 'lg:hidden' : ''"
        >
            {{ $t('dashboard.sign_out') }}
        </Link>
        <Link
            v-if="props.collapsed"
            method="post"
            href="/logout"
            as="button"
            :class="iconSignOutClass"
            :aria-label="$t('dashboard.sign_out')"
            :title="$t('dashboard.sign_out')"
        >
            <Icon
                :name="['fas', 'right-from-bracket']"
                size="sm"
            />
        </Link>
    </div>
</template>
