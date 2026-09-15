<script setup>
import { Toast } from '../components/ui/toast';
import { Icon } from '../components/ui/icon';
import { buttonVariants } from '../components/ui/button';
import { useInertiaErrorToast } from '../composables/useInertiaErrorToast';
import { cn } from '../lib/utils';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    title: {
        type: String,
        required: true,
    },
    breadcrumbs: {
        type: Array,
        default: () => [],
    },
    settingsNav: {
        type: Boolean,
        default: false,
    },
});

const page = usePage();
useInertiaErrorToast();

const user = computed(() => page.props.auth?.user);
const eventName = computed(() => page.props.activeEvent?.name ?? null);
const orgName = computed(() => page.props.organization?.name ?? null);
const currentPath = computed(() => page.url.split('?')[0]);

const navItems = [
    {
        key: 'home',
        href: '/dashboard',
        icon: ['fas', 'house'],
        enabled: true,
    },
    {
        key: 'artists',
        href: null,
        icon: ['fas', 'music'],
        enabled: false,
    },
    {
        key: 'vendors',
        href: null,
        icon: ['fas', 'store'],
        enabled: false,
    },
    {
        key: 'patrons',
        href: null,
        icon: ['fas', 'address-book'],
        enabled: false,
    },
    {
        key: 'crew',
        href: null,
        icon: ['fas', 'users'],
        enabled: false,
    },
];

const settingsActive = computed(
    () =>
        currentPath.value === '/settings' ||
        currentPath.value.startsWith('/settings/'),
);

const crumbItems = computed(() => {
    if (props.breadcrumbs.length > 0) {
        return props.breadcrumbs;
    }

    return [{ label: props.title }];
});

const isActive = (href) => {
    if (!href) {
        return false;
    }

    return (
        currentPath.value === href || currentPath.value.startsWith(`${href}/`)
    );
};

const navItemClass = (item) => {
    if (!item.enabled) {
        return 'cursor-default text-charcoal/35';
    }

    if (isActive(item.href)) {
        return 'bg-primary/10 text-primary';
    }

    return 'text-charcoal/80 hover:bg-page hover:text-charcoal';
};

const settingsClass = computed(() => {
    if (settingsActive.value) {
        return 'bg-primary/10 text-primary';
    }

    return 'text-charcoal/80 hover:bg-page hover:text-charcoal';
});

const signOutClass = cn(
    buttonVariants({
        variant: 'outline',
        size: 'sm',
    }),
    'w-full justify-start',
);
</script>

<template>
    <Head :title="title" />

    <div class="flex min-h-screen bg-page text-charcoal antialiased">
        <aside
            class="flex w-56 shrink-0 flex-col border-r border-line bg-ground"
            :aria-label="$t('nav.sidebar')"
        >
            <div class="border-b border-line px-4 py-4">
                <div class="flex items-center gap-2.5">
                    <div
                        class="inline-flex h-8 w-8 items-center justify-center rounded-lg bg-primary text-xs font-bold text-white"
                        aria-hidden="true"
                    >
                        {{ $t('app.mark') }}
                    </div>
                    <div class="min-w-0">
                        <span class="block truncate text-[15px] font-bold">
                            {{ orgName || $t('app.name') }}
                        </span>
                        <span
                            v-if="eventName"
                            class="mt-0.5 block truncate text-xs text-muted"
                        >
                            {{ eventName }}
                        </span>
                    </div>
                </div>
            </div>

            <nav class="flex flex-1 flex-col gap-1 px-2 py-3">
                <component
                    :is="item.enabled ? Link : 'span'"
                    v-for="item in navItems"
                    :key="item.key"
                    :href="item.enabled ? item.href : undefined"
                    class="inline-flex items-center gap-2.5 rounded-lg px-3 py-2 text-[13px] font-semibold no-underline"
                    :class="navItemClass(item)"
                    :aria-current="
                        item.enabled && isActive(item.href) ? 'page' : undefined
                    "
                    :aria-disabled="item.enabled ? undefined : 'true'"
                >
                    <Icon
                        :name="item.icon"
                        size="sm"
                        fixed-width
                    />
                    {{ $t(`nav.${item.key}`) }}
                </component>
            </nav>

            <div class="mt-auto space-y-2 border-t border-line px-3 py-3">
                <Link
                    href="/settings/events"
                    class="inline-flex w-full items-center gap-2.5 rounded-lg px-3 py-2 text-[13px] font-semibold no-underline"
                    :class="settingsClass"
                    :aria-current="settingsActive ? 'page' : undefined"
                >
                    <Icon
                        :name="['fas', 'gear']"
                        size="sm"
                        fixed-width
                    />
                    {{ $t('nav.settings') }}
                </Link>
                <div class="min-w-0 px-2">
                    <p class="m-0 truncate text-xs font-semibold text-charcoal">
                        {{
                            user?.name || user?.email || $t('dashboard.unknown')
                        }}
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
                >
                    {{ $t('dashboard.sign_out') }}
                </Link>
            </div>
        </aside>

        <aside
            v-if="settingsNav"
            class="flex w-52 shrink-0 flex-col border-r border-line bg-ground"
            :aria-label="$t('settings.nav.label')"
        >
            <slot name="settings-nav" />
        </aside>

        <div class="flex min-w-0 flex-1 flex-col">
            <header
                class="flex h-14 shrink-0 items-center border-b border-line bg-ground px-6"
            >
                <nav
                    class="flex items-center gap-2 text-sm text-muted"
                    :aria-label="$t('nav.breadcrumbs')"
                >
                    <template
                        v-for="(crumb, index) in crumbItems"
                        :key="`${crumb.label}-${index}`"
                    >
                        <span
                            v-if="index > 0"
                            class="text-line"
                            aria-hidden="true"
                        >
                            /
                        </span>
                        <Link
                            v-if="crumb.href"
                            :href="crumb.href"
                            class="font-medium text-muted no-underline hover:text-charcoal"
                        >
                            {{ crumb.label }}
                        </Link>
                        <span
                            v-else
                            class="font-semibold text-charcoal"
                            aria-current="page"
                        >
                            {{ crumb.label }}
                        </span>
                    </template>
                </nav>
            </header>

            <main class="flex-1 px-6 py-8">
                <div class="mx-auto w-full max-w-[1200px]">
                    <slot />
                </div>
            </main>
        </div>

        <Toast />
    </div>
</template>
