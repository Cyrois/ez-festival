<script setup>
import SetupLayout from '../../layouts/SetupLayout.vue';
import { useForm } from '@inertiajs/vue3';

const props = defineProps({
    organization: { type: Object, required: true },
    event: { type: Object, default: null },
    timezones: { type: Array, required: true },
    currentStep: { type: Number, required: true },
});

const form = useForm({
    name: props.event?.name ?? '',
    starts_on: props.event?.starts_on ?? '',
    ends_on: props.event?.ends_on ?? '',
    timezone: props.event?.timezone ?? 'America/Vancouver',
});

const submit = () => form.post('/setup/event');
const skip = () => form.post('/setup/event/skip');
</script>

<template>
    <SetupLayout
        :title="$t('setup.event.title')"
        :crumb="$t('setup.crumbs.event')"
        :current-step="currentStep"
        :organization-name="organization.name"
        :event-name="event?.name"
    >
        <div class="mb-3">
            <h2 class="m-0 mb-1 text-base font-bold">{{ $t('setup.event.heading') }}</h2>
            <p class="m-0 text-[11px] leading-snug text-muted">{{ $t('setup.event.lead') }}</p>
        </div>

        <form @submit.prevent="submit">
            <label class="mb-1 block text-[10px] font-bold" for="name">{{ $t('setup.event.name') }}</label>
            <input
                id="name"
                v-model="form.name"
                type="text"
                required
                class="mb-2 box-border h-[30px] w-full rounded-md border border-line bg-white px-2 text-[11px] text-charcoal outline-none focus:border-brand"
            />
            <p v-if="form.errors.name" class="mb-2 text-[11px] text-danger">{{ form.errors.name }}</p>

            <div class="mb-2 grid grid-cols-2 gap-2">
                <div>
                    <label class="mb-1 block text-[10px] font-bold" for="starts_on">{{ $t('setup.event.starts_on') }}</label>
                    <input
                        id="starts_on"
                        v-model="form.starts_on"
                        type="date"
                        required
                        class="box-border h-[30px] w-full rounded-md border border-line bg-white px-2 text-[11px] text-charcoal outline-none focus:border-brand"
                    />
                    <p v-if="form.errors.starts_on" class="mt-1 text-[11px] text-danger">{{ form.errors.starts_on }}</p>
                </div>
                <div>
                    <label class="mb-1 block text-[10px] font-bold" for="ends_on">{{ $t('setup.event.ends_on') }}</label>
                    <input
                        id="ends_on"
                        v-model="form.ends_on"
                        type="date"
                        required
                        class="box-border h-[30px] w-full rounded-md border border-line bg-white px-2 text-[11px] text-charcoal outline-none focus:border-brand"
                    />
                    <p v-if="form.errors.ends_on" class="mt-1 text-[11px] text-danger">{{ form.errors.ends_on }}</p>
                </div>
            </div>

            <label class="mb-1 block text-[10px] font-bold" for="timezone">{{ $t('setup.event.timezone') }}</label>
            <select
                id="timezone"
                v-model="form.timezone"
                required
                class="mb-2 box-border h-[30px] w-full rounded-md border border-line bg-white px-2 text-[11px] text-charcoal outline-none focus:border-brand"
            >
                <option v-for="tz in timezones" :key="tz" :value="tz">{{ tz }}</option>
            </select>
            <p v-if="form.errors.timezone" class="mb-2 text-[11px] text-danger">{{ form.errors.timezone }}</p>

            <div class="mt-2 flex justify-end gap-1.5">
                <button
                    type="button"
                    class="h-7 cursor-pointer rounded-md border-none bg-page px-2.5 text-[11px] font-bold text-charcoal"
                    :disabled="form.processing"
                    @click="skip"
                >
                    {{ $t('setup.actions.skip') }}
                </button>
                <button
                    type="submit"
                    class="h-7 cursor-pointer rounded-md border-none bg-brand px-2.5 text-[11px] font-bold text-white hover:bg-brand-hover disabled:opacity-70"
                    :disabled="form.processing"
                >
                    {{ $t('setup.actions.save_continue') }}
                </button>
            </div>
        </form>
    </SetupLayout>
</template>
