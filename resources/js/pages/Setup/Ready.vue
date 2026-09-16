<script setup>
import SetupLayout from '../../layouts/SetupLayout.vue';
import { Button } from '../../components/ui/button';
import { Icon } from '../../components/ui/icon';
import { router } from '@inertiajs/vue3';
import { ref } from 'vue';

defineProps({
    client: { type: Object, required: true },
    currentStep: { type: Number, required: true },
});

const enterBusy = ref(false);

const enterApp = () => {
    if (enterBusy.value) {
        return;
    }
    enterBusy.value = true;
    router.post(
        '/setup/ready',
        {},
        {
            onFinish: () => {
                enterBusy.value = false;
            },
        },
    );
};
</script>

<template>
    <SetupLayout
        :title="$t('setup.ready.title')"
        :current-step="currentStep"
        :client-name="client.name"
    >
        <div
            class="mx-auto mb-6 flex h-24 w-24 items-center justify-center rounded-full bg-success-soft text-success"
            aria-hidden="true"
        >
            <Icon
                :name="['fas', 'check']"
                class="text-[48px]"
            />
        </div>

        <h1 class="m-0 mb-2.5 text-[28px] font-bold tracking-tight">
            {{ $t('setup.ready.heading') }}
        </h1>
        <p
            class="mx-auto mb-3 max-w-[40ch] text-[15px] leading-snug text-muted"
        >
            {{ $t('setup.ready.lead') }}
        </p>
        <p class="mx-auto mb-7 max-w-[42ch] text-sm leading-snug text-charcoal">
            {{ $t('setup.ready.note') }}
        </p>

        <div class="flex justify-center">
            <Button
                type="button"
                variant="primary"
                size="lg"
                class="min-w-40"
                :loading="enterBusy"
                :disabled="enterBusy"
                @click="enterApp"
            >
                {{ $t('setup.actions.next') }}
            </Button>
        </div>
    </SetupLayout>
</template>
