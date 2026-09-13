<script setup>
import { Toast } from '../components/ui/toast';
import { useFlashToast } from '../composables/useFlashToast';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { trans } from 'laravel-vue-i18n';
import { computed, onUnmounted, watch } from 'vue';

const props = defineProps({
    title: { type: String, required: true },
    currentStep: { type: Number, required: true },
    organizationName: { type: String, default: '' },
});

const page = usePage();
const { showError } = useFlashToast();

const orgName = computed(
    () => props.organizationName || page.props.organization?.name || '',
);

const orgSetupLabel = computed(() => {
    if (!orgName.value) {
        return trans('nav.setup');
    }

    return trans('setup.shell.org_setup', { name: orgName.value });
});

const steps = [
    { n: 1, key: 'event', route: '/setup/event' },
    { n: 2, key: 'locations', route: '/setup/locations' },
    { n: 3, key: 'vendor_types', route: '/setup/vendor-types' },
    { n: 4, key: 'artist_types', route: '/setup/artist-types' },
];

const stepClass = (n) => {
    if (n === props.currentStep) {
        return 'bg-primary text-white';
    }

    return 'bg-line text-charcoal/70';
};

const stepNumberClass = (n) => {
    if (n === props.currentStep) {
        return 'bg-ground/25 text-white';
    }

    return 'bg-charcoal/10 text-charcoal/70';
};

watch(
    () => page.props.flash?.error,
    (error) => {
        if (error) {
            showError(error);
        }
    },
    { immediate: true },
);

const removeInvalidListener = router.on('invalid', (event) => {
    event.preventDefault();
    showError(trans('setup.errors.generic'));
});

onUnmounted(() => {
    removeInvalidListener();
});
</script>

<template>
    <Head :title="title" />

    <div
        class="min-h-screen bg-page px-6 pt-10 pb-16 text-charcoal antialiased"
    >
        <div class="mx-auto w-full max-w-[720px]">
            <div class="mb-7 flex items-center gap-2.5">
                <div
                    class="inline-flex h-9 w-9 items-center justify-center rounded-[9px] bg-primary text-[13px] font-bold text-white"
                    aria-hidden="true"
                >
                    {{ $t('app.mark') }}
                </div>
                <div class="min-w-0">
                    <strong class="block truncate text-[15px] font-bold">{{
                        $t('app.name')
                    }}</strong>
                    <span class="mt-0.5 block truncate text-xs text-muted">{{
                        orgSetupLabel
                    }}</span>
                </div>
            </div>

            <nav
                v-if="currentStep <= 4"
                class="mb-7 flex flex-wrap gap-2"
                :aria-label="$t('nav.setup')"
            >
                <Link
                    v-for="step in steps"
                    :key="step.n"
                    :href="step.route"
                    class="inline-flex h-8 items-center gap-2 rounded-full px-3 text-xs font-bold no-underline"
                    :class="stepClass(step.n)"
                    :aria-current="step.n === currentStep ? 'step' : undefined"
                >
                    <span
                        class="inline-flex h-[18px] w-[18px] items-center justify-center rounded-full text-[11px] font-bold"
                        :class="stepNumberClass(step.n)"
                        aria-hidden="true"
                    >
                        {{ step.n }}
                    </span>
                    {{ $t(`setup.steps.${step.key}`) }}
                </Link>
            </nav>

            <slot />
        </div>

        <Toast />
    </div>
</template>
