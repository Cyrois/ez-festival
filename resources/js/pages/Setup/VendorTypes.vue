<script setup>
import SetupLayout from '../../layouts/SetupLayout.vue';
import { Link, useForm, router } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    organization: { type: Object, required: true },
    event: { type: Object, default: null },
    types: { type: Array, required: true },
    currentStep: { type: Number, required: true },
});

const showAdd = ref(false);
const editingId = ref(null);

const addForm = useForm({ name: '' });
const editForm = useForm({ name: '' });

const submitAdd = () => {
    addForm.post('/setup/vendor-types', {
        preserveScroll: true,
        onSuccess: () => {
            addForm.reset();
            showAdd.value = false;
        },
    });
};

const startEdit = (type) => {
    editingId.value = type.id;
    editForm.name = type.name;
};

const submitEdit = (type) => {
    editForm.put(`/setup/vendor-types/${type.id}`, {
        preserveScroll: true,
        onSuccess: () => {
            editingId.value = null;
        },
    });
};

const continueSetup = () => router.post('/setup/vendor-types/continue');
const skip = () => router.post('/setup/vendor-types/skip');
</script>

<template>
    <SetupLayout
        :title="$t('setup.vendor_types.title')"
        :crumb="$t('setup.crumbs.vendor_types')"
        :current-step="currentStep"
        :organization-name="organization.name"
        :event-name="event?.name"
    >
        <div class="mb-3 flex items-start justify-between gap-2.5">
            <div>
                <h2 class="m-0 mb-1 text-base font-bold">{{ $t('setup.vendor_types.heading') }}</h2>
                <p class="m-0 text-[11px] leading-snug text-muted">{{ $t('setup.vendor_types.lead') }}</p>
            </div>
            <button
                type="button"
                class="h-7 shrink-0 cursor-pointer rounded-md border-none bg-brand px-2.5 text-[11px] font-bold text-white hover:bg-brand-hover"
                @click="showAdd = !showAdd"
            >
                {{ $t('setup.vendor_types.add') }}
            </button>
        </div>

        <form
            v-if="showAdd"
            class="mb-2 rounded-lg border border-line bg-white p-2.5"
            @submit.prevent="submitAdd"
        >
            <label class="mb-1 block text-[10px] font-bold" for="vt-name">{{ $t('setup.types.name') }}</label>
            <input
                id="vt-name"
                v-model="addForm.name"
                type="text"
                required
                class="mb-2 box-border h-[30px] w-full rounded-md border border-line bg-white px-2 text-[11px] outline-none focus:border-brand"
            />
            <div class="flex justify-end gap-1.5">
                <button
                    type="button"
                    class="h-7 cursor-pointer rounded-md border border-line bg-white px-2.5 text-[11px] font-bold"
                    @click="showAdd = false"
                >
                    {{ $t('setup.actions.cancel') }}
                </button>
                <button
                    type="submit"
                    class="h-7 cursor-pointer rounded-md border-none bg-brand px-2.5 text-[11px] font-bold text-white"
                    :disabled="addForm.processing"
                >
                    {{ $t('setup.actions.add') }}
                </button>
            </div>
        </form>

        <div v-if="types.length" class="mb-2 overflow-hidden rounded-lg border border-line">
            <div
                v-for="type in types"
                :key="type.id"
                class="flex items-center justify-between border-b border-line px-2.5 py-2 text-[11px] last:border-b-0"
            >
                <template v-if="editingId === type.id">
                    <form class="flex w-full items-center gap-1.5" @submit.prevent="submitEdit(type)">
                        <input
                            v-model="editForm.name"
                            type="text"
                            required
                            class="box-border h-[30px] flex-1 rounded-md border border-line px-2 text-[11px]"
                        />
                        <button
                            type="button"
                            class="h-7 cursor-pointer rounded-md border border-line bg-white px-2 text-[11px] font-bold"
                            @click="editingId = null"
                        >
                            {{ $t('setup.actions.cancel') }}
                        </button>
                        <button
                            type="submit"
                            class="h-7 cursor-pointer rounded-md border-none bg-brand px-2 text-[11px] font-bold text-white"
                        >
                            {{ $t('setup.actions.save') }}
                        </button>
                    </form>
                </template>
                <template v-else>
                    <strong class="font-bold">{{ type.name }}</strong>
                    <button
                        type="button"
                        class="cursor-pointer border-none bg-transparent text-[10px] text-muted"
                        @click="startEdit(type)"
                    >
                        {{ $t('setup.actions.edit') }}
                    </button>
                </template>
            </div>
        </div>
        <div
            v-else
            class="mb-2 rounded-lg border border-dashed border-line px-3.5 py-3.5 text-center text-[11px] text-muted"
        >
            {{ $t('setup.vendor_types.empty') }}
        </div>

        <p class="mb-2 text-[10px] leading-snug text-muted">{{ $t('setup.vendor_types.note') }}</p>

        <div class="mt-2 flex justify-end gap-1.5">
            <Link
                href="/setup/locations"
                class="inline-flex h-7 items-center rounded-md border-none bg-page px-2.5 text-[11px] font-bold text-charcoal no-underline"
            >
                {{ $t('setup.actions.back') }}
            </Link>
            <button
                type="button"
                class="h-7 cursor-pointer rounded-md border-none bg-page px-2.5 text-[11px] font-bold text-charcoal"
                @click="skip"
            >
                {{ $t('setup.actions.skip') }}
            </button>
            <button
                type="button"
                class="h-7 cursor-pointer rounded-md border-none bg-brand px-2.5 text-[11px] font-bold text-white hover:bg-brand-hover"
                @click="continueSetup"
            >
                {{ $t('setup.actions.save_continue') }}
            </button>
        </div>
    </SetupLayout>
</template>
