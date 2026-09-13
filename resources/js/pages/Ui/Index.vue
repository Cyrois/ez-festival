<script setup>
import { Head } from '@inertiajs/vue3';
import { trans } from 'laravel-vue-i18n';
import { ref } from 'vue';
import { Button } from '../../components/ui/button';
import { Input } from '../../components/ui/input';
import { FormField } from '../../components/ui/form-field';
import { Textarea } from '../../components/ui/textarea';
import { Select } from '../../components/ui/select';
import { Checkbox } from '../../components/ui/checkbox';
import { Radio } from '../../components/ui/radio';
import { Switch } from '../../components/ui/switch';
import { Badge } from '../../components/ui/badge';
import { Label } from '../../components/ui/label';
import { Tag } from '../../components/ui/tag';
import LayoutDataDemo from './LayoutDataDemo.vue';
import { Toast } from '../../components/ui/toast';
import { useFlashToast } from '../../composables/useFlashToast';

const sample = ref('Default');
const sampleError = ref('Bad value');
const notes = ref('');
const locationType = ref('');
const locationTypeInvalid = ref('');
const requiresPower = ref(true);
const siteType = ref('stage');
const published = ref(false);
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
                    <Button variant="outline">
                        {{ $t('ui.demo.variant.outline') }}
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
                        variant="primary"
                        loading
                    >
                        {{ $t('ui.demo.loading') }}
                    </Button>
                    <Button
                        variant="outline"
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
                    {{ $t('ui.demo.textarea') }}
                </h2>
                <p class="m-0 mb-4 text-xs text-muted">
                    {{ $t('ui.demo.textarea_lead') }}
                </p>
                <FormField
                    :label="$t('ui.demo.textarea_label')"
                    :hint="$t('ui.demo.textarea_hint')"
                    class="max-w-md"
                >
                    <template #default="{ id, invalid }">
                        <Textarea
                            :id="id"
                            v-model="notes"
                            :invalid="invalid"
                            :placeholder="$t('ui.demo.textarea_placeholder')"
                            rows="4"
                        />
                    </template>
                </FormField>
            </section>

            <section
                class="mb-4 rounded-xl border border-line bg-ground px-5 py-5"
            >
                <h2 class="m-0 mb-1 text-base font-bold">
                    {{ $t('ui.demo.select') }}
                </h2>
                <p class="m-0 mb-4 text-xs text-muted">
                    {{ $t('ui.demo.select_lead') }}
                </p>
                <div class="flex flex-wrap gap-4">
                    <FormField
                        :label="$t('ui.demo.select_label')"
                        class="min-w-[200px]"
                    >
                        <template #default="{ id, invalid }">
                            <Select
                                :id="id"
                                v-model="locationType"
                                :invalid="invalid"
                            >
                                <option value="">
                                    {{ $t('ui.demo.select_placeholder') }}
                                </option>
                                <option value="stage">
                                    {{ $t('ui.demo.select.stage') }}
                                </option>
                                <option value="tent">
                                    {{ $t('ui.demo.select.tent') }}
                                </option>
                                <option value="indoor">
                                    {{ $t('ui.demo.select.indoor') }}
                                </option>
                            </Select>
                        </template>
                    </FormField>
                    <FormField
                        :label="$t('ui.demo.select_label')"
                        :error="$t('ui.demo.select_error')"
                        class="min-w-[200px]"
                    >
                        <template #default="{ id, invalid }">
                            <Select
                                :id="id"
                                v-model="locationTypeInvalid"
                                :invalid="invalid"
                            >
                                <option value="">
                                    {{ $t('ui.demo.select_placeholder') }}
                                </option>
                                <option value="stage">
                                    {{ $t('ui.demo.select.stage') }}
                                </option>
                                <option value="tent">
                                    {{ $t('ui.demo.select.tent') }}
                                </option>
                            </Select>
                        </template>
                    </FormField>
                </div>
            </section>

            <section
                class="mb-4 rounded-xl border border-line bg-ground px-5 py-5"
            >
                <h2 class="m-0 mb-1 text-base font-bold">
                    {{ $t('ui.demo.controls') }}
                </h2>
                <p class="m-0 mb-4 text-xs text-muted">
                    {{ $t('ui.demo.controls_lead') }}
                </p>
                <div class="flex flex-col gap-4">
                    <div class="flex flex-wrap items-center gap-4">
                        <Checkbox
                            v-model="requiresPower"
                            :label="$t('ui.demo.checkbox_label')"
                        />
                        <Checkbox
                            :model-value="true"
                            disabled
                            :label="$t('ui.demo.checkbox_disabled')"
                        />
                        <Switch
                            v-model="published"
                            :label="$t('ui.demo.switch_label')"
                        />
                    </div>
                    <div>
                        <Label class="mb-2 block">
                            {{ $t('ui.demo.radio_legend') }}
                        </Label>
                        <div class="flex flex-wrap items-center gap-4">
                            <Radio
                                v-model="siteType"
                                value="stage"
                                name="site-type"
                                :label="$t('ui.demo.radio.stage')"
                            />
                            <Radio
                                v-model="siteType"
                                value="tent"
                                name="site-type"
                                :label="$t('ui.demo.radio.tent')"
                            />
                            <Radio
                                v-model="siteType"
                                value="indoor"
                                name="site-type"
                                :label="$t('ui.demo.radio.indoor')"
                            />
                        </div>
                    </div>
                </div>
            </section>

            <section
                class="mb-4 rounded-xl border border-line bg-ground px-5 py-5"
            >
                <h2 class="m-0 mb-1 text-base font-bold">
                    {{ $t('ui.demo.badges') }}
                </h2>
                <p class="m-0 mb-4 text-xs text-muted">
                    {{ $t('ui.demo.badges_lead') }}
                </p>
                <div class="flex flex-wrap items-center gap-2">
                    <Badge variant="neutral">
                        {{ $t('ui.demo.badge.neutral') }}
                    </Badge>
                    <Badge variant="primary">
                        {{ $t('ui.demo.badge.primary') }}
                    </Badge>
                    <Badge variant="success">
                        {{ $t('ui.demo.badge.success') }}
                    </Badge>
                    <Badge variant="warning">
                        {{ $t('ui.demo.badge.warning') }}
                    </Badge>
                    <Badge variant="danger">
                        {{ $t('ui.demo.badge.danger') }}
                    </Badge>
                    <Badge variant="outline">
                        {{ $t('ui.demo.badge.outline') }}
                    </Badge>
                    <Badge
                        variant="primary"
                        pill
                    >
                        {{ $t('ui.demo.badge.pill') }}
                    </Badge>
                </div>
            </section>

            <section
                class="mb-4 rounded-xl border border-line bg-ground px-5 py-5"
            >
                <h2 class="m-0 mb-1 text-base font-bold">
                    {{ $t('ui.demo.tags') }}
                </h2>
                <p class="m-0 mb-4 text-xs text-muted">
                    {{ $t('ui.demo.tags_lead') }}
                </p>
                <div class="flex flex-wrap items-center gap-2">
                    <Tag
                        :name="$t('ui.demo.tag.headliner')"
                        color="primary"
                    />
                    <Tag
                        :name="$t('ui.demo.tag.local')"
                        color="secondary"
                    />
                    <Tag
                        :name="$t('ui.demo.tag.vip')"
                        color="warning"
                    />
                    <Tag
                        :name="$t('ui.demo.tag.soundcheck')"
                        color="success"
                    />
                    <Tag
                        :name="$t('ui.demo.tag.custom')"
                        color="#3D6B8A"
                    />
                </div>
            </section>

            <LayoutDataDemo />

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
                        variant="outline"
                        size="sm"
                        @click="triggerSuccess"
                    >
                        {{ $t('ui.demo.toast.trigger_success') }}
                    </Button>
                    <Button
                        variant="outline"
                        size="sm"
                        @click="triggerError"
                    >
                        {{ $t('ui.demo.toast.trigger_error') }}
                    </Button>
                    <Button
                        variant="outline"
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
