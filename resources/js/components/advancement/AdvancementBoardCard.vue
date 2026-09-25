<script setup>
import { Avatar } from '../ui/avatar';
import { Link } from '@inertiajs/vue3';

defineProps({
    name: { type: String, required: true },
    subtitle: { type: String, default: '' },
    href: { type: String, required: true },
});
</script>

<template>
    <article class="rounded-lg border border-line bg-ground p-3 shadow-sm">
        <div class="flex min-w-0 items-center gap-2.5">
            <Avatar
                :name="name"
                size="sm"
            />
            <div class="min-w-0">
                <Link
                    :href="href"
                    class="block truncate font-semibold text-charcoal no-underline hover:text-primary hover:underline"
                >
                    {{ name }}
                </Link>
                <p
                    v-if="subtitle"
                    class="mt-0.5 mb-0 truncate text-xs text-muted"
                >
                    {{ subtitle }}
                </p>
            </div>
        </div>
        <slot />
    </article>
</template>
