<script setup>
import { Toast } from '../components/ui/toast';
import { useInertiaErrorToast } from '../composables/useInertiaErrorToast';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { trans } from 'laravel-vue-i18n';
import { computed } from 'vue';

const props = defineProps({
    title: { type: String, required: true },
    currentStep: { type: Number, required: true },
    organizationName: { type: String, default: '' },
});

const page = usePage();
useInertiaErrorToast();

const resolvedOrganizationName = computed(
    () => props.organizationName || page.props.organization?.name || '',
);

const hasActiveEvent = computed(() => Boolean(page.props.activeEvent?.id));

const isComplete = computed(() => props.currentStep > 4);

const organizationSetupLabel = computed(() => {
    if (!resolvedOrganizationName.value) {
        return trans('nav.setup');
    }

    return trans('setup.shell.organization_setup', {
        name: resolvedOrganizationName.value,
    });
});

const steps = [
    { n: 1, key: 'event', route: '/setup/event' },
    { n: 2, key: 'locations', route: '/setup/locations' },
    { n: 3, key: 'vendor_types', route: '/setup/vendor-types' },
    { n: 4, key: 'artist_types', route: '/setup/artist-types' },
];

const stepEnabled = (n) => n === 1 || hasActiveEvent.value;

const stepClass = (n) => {
    if (n === props.currentStep) {
        return 'bg-primary text-white';
    }
    if (!stepEnabled(n)) {
        return 'bg-line text-charcoal/40 pointer-events-none';
    }
    if (n < props.currentStep) {
        return 'bg-primary/10 text-primary';
    }

    return 'bg-line text-charcoal/70';
};

const stepNumberClass = (n) => {
    if (n === props.currentStep) {
        return 'bg-ground/25 text-white';
    }
    if (!stepEnabled(n)) {
        return 'bg-charcoal/5 text-charcoal/40';
    }
    if (n < props.currentStep) {
        return 'bg-primary/15 text-primary';
    }

    return 'bg-charcoal/10 text-charcoal/70';
};
</script>

<template>
    <Head :title="title" />

    <div
        class="min-h-screen bg-page px-4 text-charcoal antialiased"
        :class="isComplete ? 'flex items-center py-10 pb-16' : 'pt-10 pb-16'"
    >
        <div
            class="mx-auto w-full"
            :class="isComplete ? 'max-w-[560px] text-center' : 'max-w-[720px]'"
        >
            <div
                class="mb-7 flex items-center gap-2.5"
                :class="isComplete ? 'justify-center' : ''"
            >
                <div
                    class="inline-flex h-9 w-9 items-center justify-center rounded-[9px] bg-primary text-[13px] font-bold text-white"
                    aria-hidden="true"
                >
                    {{ $t('app.mark') }}
                </div>
                <div class="min-w-0">
                    <strong
                        class="block text-[15px] font-bold"
                        :class="isComplete ? '' : 'truncate'"
                        >{{ $t('app.name') }}</strong
                    >
                    <span
                        v-if="!isComplete"
                        class="mt-0.5 block truncate text-xs text-muted"
                        >{{ organizationSetupLabel }}</span
                    >
                </div>
            </div>

            <nav
                v-if="!isComplete"
                class="mb-7 flex flex-wrap gap-2"
                :aria-label="$t('nav.setup')"
            >
                <component
                    :is="stepEnabled(step.n) ? Link : 'span'"
                    v-for="step in steps"
                    :key="step.n"
                    :href="stepEnabled(step.n) ? step.route : undefined"
                    class="inline-flex h-8 items-center gap-2 rounded-full px-3 text-xs font-bold no-underline"
                    :class="stepClass(step.n)"
                    :aria-current="step.n === currentStep ? 'step' : undefined"
                    :aria-disabled="stepEnabled(step.n) ? undefined : 'true'"
                >
                    <span
                        class="inline-flex h-[18px] w-[18px] items-center justify-center rounded-full text-[11px] font-bold"
                        :class="stepNumberClass(step.n)"
                        aria-hidden="true"
                    >
                        {{ step.n }}
                    </span>
                    {{ $t(`setup.steps.${step.key}`) }}
                </component>
            </nav>

            <slot />
        </div>

        <Toast />
    </div>
</template>
