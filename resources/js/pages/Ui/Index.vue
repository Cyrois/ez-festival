<script setup>
import { Head } from '@inertiajs/vue3';
import { trans } from 'laravel-vue-i18n';
import { ref } from 'vue';
import { Button } from '../../components/ui/button';
import { Input } from '../../components/ui/input';
import { FormField } from '../../components/ui/form-field';
import { Toast } from '../../components/ui/toast';
import { useFlashToast } from '../../composables/useFlashToast';

const sample = ref('Default');
const sampleError = ref('Bad value');
const { showSuccess, showError, showInfo } = useFlashToast();

const triggerSuccess = () =>
    showSuccess(
        trans('ui.demo.toast.success_body'),
        trans('ui.demo.toast.success_title'),
    );

const triggerError = () =>
    showError(
        trans('ui.demo.toast.error_body'),
        trans('ui.demo.toast.error_title'),
    );

const triggerInfo = () =>
    showInfo(
        trans('ui.demo.toast.info_body'),
        trans('ui.demo.toast.info_title'),
    );
</script>

<template>
    <Head :title="$t('ui.demo.title')" />

    <div class="min-h-screen bg-page px-6 py-8 text-charcoal antialiased">
        <div class="mx-auto max-w-4xl">
            <header class="mb-7">
                <h1 class="m-0 mb-1.5 text-3xl font-bold tracking-tight">
                    {{ $t('ui.demo.title') }}
                </h1>
                <p class="m-0 max-w-prose text-[15px] text-muted">
                    {{ $t('ui.demo.lead') }}
                </p>
            </header>

            <section
                class="mb-4 rounded-xl border border-line bg-ground px-5 py-5"
            >
                <h2 class="m-0 mb-1 text-base font-bold">
                    {{ $t('ui.demo.buttons') }}
                </h2>
                <p class="m-0 mb-4 text-xs text-muted">
                    {{ $t('ui.demo.buttons_lead') }}
                </p>
                <div class="mb-3 flex flex-wrap items-center gap-2.5">
                    <Button variant="primary">
                        {{ $t('ui.demo.variant.primary') }}
                    </Button>
                    <Button variant="secondary">
                        {{ $t('ui.demo.variant.secondary') }}
                    </Button>
                    <Button variant="soft">
                        {{ $t('ui.demo.variant.soft') }}
                    </Button>
                    <Button variant="ghost">
                        {{ $t('ui.demo.variant.ghost') }}
                    </Button>
                    <Button variant="danger">
                        {{ $t('ui.demo.variant.danger') }}
                    </Button>
                    <Button variant="outline-danger">
                        {{ $t('ui.demo.variant.outline_danger') }}
                    </Button>
                    <Button
                        variant="primary"
                        disabled
                    >
                        {{ $t('ui.demo.disabled') }}
                    </Button>
                    <Button
                        variant="secondary"
                        size="icon"
                        :aria-label="$t('ui.demo.icon')"
                    >
                        ⋯
                    </Button>
                </div>
                <div class="flex flex-wrap items-center gap-2.5">
                    <Button
                        variant="primary"
                        size="sm"
                    >
                        {{ $t('ui.demo.size.sm') }}
                    </Button>
                    <Button
                        variant="primary"
                        size="md"
                    >
                        {{ $t('ui.demo.size.md') }}
                    </Button>
                    <Button
                        variant="primary"
                        size="lg"
                    >
                        {{ $t('ui.demo.size.lg') }}
                    </Button>
                    <Button variant="primary">
                        <span aria-hidden="true">+</span>
                        {{ $t('ui.demo.with_icon') }}
                    </Button>
                </div>
            </section>

            <section
                class="mb-4 rounded-xl border border-line bg-ground px-5 py-5"
            >
                <h2 class="m-0 mb-1 text-base font-bold">
                    {{ $t('ui.demo.inputs') }}
                </h2>
                <p class="m-0 mb-4 text-xs text-muted">
                    {{ $t('ui.demo.inputs_lead') }}
                </p>
                <div class="flex flex-wrap gap-4">
                    <FormField
                        :label="$t('ui.demo.label')"
                        :hint="$t('ui.demo.helper')"
                        class="min-w-[200px]"
                    >
                        <template #default="{ id, invalid }">
                            <Input
                                :id="id"
                                v-model="sample"
                                :invalid="invalid"
                            />
                        </template>
                    </FormField>
                    <FormField
                        :label="$t('ui.demo.error_label')"
                        :error="$t('ui.demo.required')"
                        class="min-w-[200px]"
                    >
                        <template #default="{ id, invalid }">
                            <Input
                                :id="id"
                                v-model="sampleError"
                                :invalid="invalid"
                            />
                        </template>
                    </FormField>
                </div>
            </section>

            <section
                class="mb-4 rounded-xl border border-line bg-ground px-5 py-5"
            >
                <h2 class="m-0 mb-1 text-base font-bold">
                    {{ $t('ui.demo.toasts') }}
                </h2>
                <p class="m-0 mb-4 text-xs text-muted">
                    {{ $t('ui.demo.toasts_lead') }}
                </p>
                <div class="flex flex-wrap gap-2.5">
                    <Button
                        variant="secondary"
                        size="sm"
                        @click="triggerSuccess"
                    >
                        {{ $t('ui.demo.toast.trigger_success') }}
                    </Button>
                    <Button
                        variant="secondary"
                        size="sm"
                        @click="triggerError"
                    >
                        {{ $t('ui.demo.toast.trigger_error') }}
                    </Button>
                    <Button
                        variant="secondary"
                        size="sm"
                        @click="triggerInfo"
                    >
                        {{ $t('ui.demo.toast.trigger_info') }}
                    </Button>
                </div>
            </section>
        </div>

        <Toast />
    </div>
</template>
