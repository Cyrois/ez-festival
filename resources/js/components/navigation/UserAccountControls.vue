<script setup>
import { Icon } from '../ui/icon';
import { Link } from '@inertiajs/vue3';

defineProps({
    user: {
        type: Object,
        default: null,
    },
    placement: {
        type: String,
        default: 'header',
        validator: (value) => ['header', 'sidebar'].includes(value),
    },
});

const signOutClass =
    'inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-lg border border-line bg-transparent text-muted no-underline transition-colors hover:bg-page hover:text-charcoal focus-visible:outline-none focus-visible:ring-[3px] focus-visible:ring-charcoal/20';
</script>

<template>
    <div
        class="shrink-0 items-center gap-3"
        :class="
            placement === 'header'
                ? 'ml-auto hidden lg:flex'
                : 'flex justify-between border-t border-line px-3 py-3 lg:hidden'
        "
    >
        <div
            class="min-w-0"
            :class="placement === 'header' ? 'max-w-52 text-right' : 'px-2'"
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
            :class="signOutClass"
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
