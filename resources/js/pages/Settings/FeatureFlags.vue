<script setup>
import { Badge } from '../../components/ui/badge';
import { Card, CardBody } from '../../components/ui/card';
import { Switch } from '../../components/ui/switch';
import SettingsLayout from '../../layouts/SettingsLayout.vue';
import { router } from '@inertiajs/vue3';
import { ref } from 'vue';

defineProps({
    flags: {
        type: Array,
        required: true,
    },
});

const pending = ref({});

const update = (flag, enabled) => {
    pending.value[flag.key] = true;

    router.put(
        `/settings/feature-flags/${flag.key}`,
        { enabled },
        {
            preserveScroll: true,
            onFinish: () => {
                pending.value[flag.key] = false;
            },
        },
    );
};
</script>

<template>
    <SettingsLayout :title="$t('settings.feature_flags.title')">
        <div class="mx-auto w-full max-w-3xl px-4 py-7 sm:px-6 lg:px-8">
            <div class="mb-6">
                <h1 class="m-0 text-2xl font-bold text-charcoal">
                    {{ $t('settings.feature_flags.title') }}
                </h1>
                <p class="mt-2 mb-0 text-sm text-muted">
                    {{ $t('settings.feature_flags.lead') }}
                </p>
            </div>

            <Card>
                <CardBody class="divide-y divide-line p-0">
                    <div
                        v-for="flag in flags"
                        :key="flag.key"
                        class="flex flex-col gap-4 p-5 sm:flex-row sm:items-center sm:justify-between"
                    >
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <h2
                                    class="m-0 text-base font-bold text-charcoal"
                                >
                                    {{ $t(flag.label) }}
                                </h2>
                                <Badge
                                    :variant="
                                        flag.enabled ? 'success' : 'neutral'
                                    "
                                    pill
                                >
                                    {{
                                        flag.enabled
                                            ? $t(
                                                  'settings.feature_flags.status.enabled',
                                              )
                                            : $t(
                                                  'settings.feature_flags.status.disabled',
                                              )
                                    }}
                                </Badge>
                            </div>
                            <p class="mt-1 mb-0 text-sm text-muted">
                                {{ $t(flag.description) }}
                            </p>
                        </div>
                        <Switch
                            :model-value="flag.enabled"
                            :disabled="pending[flag.key]"
                            :aria-label="$t(flag.label)"
                            @update:model-value="update(flag, $event)"
                        />
                    </div>
                </CardBody>
            </Card>
        </div>
    </SettingsLayout>
</template>
