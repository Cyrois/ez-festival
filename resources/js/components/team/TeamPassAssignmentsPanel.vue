<script setup>
import TeamPassCard from './TeamPassCard.vue';
import { Button } from '../ui/button';
import { CustomDropdown } from '../ui/custom-dropdown';
import { FormField } from '../ui/form-field';
import { Icon } from '../ui/icon';
import { Popup } from '../ui/popup';
import { Tag } from '../ui/tag';
import { trans } from 'laravel-vue-i18n';
import { computed, ref } from 'vue';

const props = defineProps({
    passes: { type: Array, default: () => [] },
    memberName: { type: String, required: true },
    canWrite: { type: Boolean, required: true },
    hired: { type: Boolean, required: true },
    errors: { type: Object, default: () => ({}) },
});

const assignments = defineModel('assignments', {
    type: Array,
    required: true,
});
const giveOpen = ref(false);
const selectedPassId = ref('');
const canMutate = computed(() => props.canWrite && props.hired);
const selectedPass = computed(() =>
    props.passes.find((pass) => pass.id === selectedPassId.value),
);
const passItems = computed(() =>
    props.passes.map((pass) => ({
        value: pass.id,
        title: pass.name,
        description: pass.full ? trans('permissions.pass_full') : '',
        disabled: pass.full,
    })),
);

const assignmentError = computed(
    () =>
        Object.entries(props.errors).find(([key]) =>
            key.startsWith('pass_assignments'),
        )?.[1] ?? '',
);

const passFor = (assignment) =>
    props.passes.find((pass) => pass.id === assignment.pass_type_id);

const openGive = () => {
    selectedPassId.value = '';
    giveOpen.value = true;
};

const closeGive = () => {
    selectedPassId.value = '';
    giveOpen.value = false;
};

const givePass = () => {
    if (!selectedPass.value) {
        return;
    }

    assignments.value.push({
        id: null,
        pass_type_id: selectedPass.value.id,
        issue_state: 'unissued',
        can_remove: true,
    });
    closeGive();
};

const removePass = (index) => {
    assignments.value.splice(index, 1);
};
</script>

<template>
    <section>
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h2 class="m-0 text-xl font-bold text-muted">
                    {{ $t('team.member.passes.title') }}
                </h2>
                <p class="mt-1 mb-0 text-xs text-muted">
                    {{ $t('team.member.passes.description') }}
                </p>
            </div>
            <Button
                v-if="canMutate"
                type="button"
                size="sm"
                :disabled="passes.length === 0"
                @click="openGive"
            >
                <Icon
                    :name="['fas', 'plus']"
                    class="mr-1.5"
                    size="sm"
                />
                {{ $t('team.member.passes.actions.give') }}
            </Button>
        </div>

        <p
            v-if="!hired"
            class="mt-4 rounded-lg border border-dashed border-line bg-page p-5 text-center text-sm text-muted"
        >
            {{ $t('team.member.passes.hired_required') }}
        </p>

        <p
            v-if="assignmentError"
            class="mt-3 mb-0 text-sm text-danger"
        >
            {{ assignmentError }}
        </p>

        <div class="mt-4 space-y-2">
            <TeamPassCard
                v-for="(assignment, index) in assignments"
                :key="assignment.id ?? `new-${index}`"
                :assignment="assignment"
                :pass="passFor(assignment)"
                :can-write="canMutate"
                @remove="removePass(index)"
            />
            <p
                v-if="assignments.length === 0"
                class="m-0 py-3 text-sm text-muted"
            >
                {{ $t('team.member.passes.empty') }}
            </p>
        </div>

        <Popup
            v-model:open="giveOpen"
            :title="$t('team.member.passes.dialog.title')"
            :accept-label="$t('team.member.passes.dialog.accept')"
            :cancel-label="$t('ui.dialog.cancel')"
            :confirm-disabled="!selectedPass"
            class="min-h-[28rem] max-w-2xl"
            @accept="givePass"
            @cancel="closeGive"
        >
            <template #description>
                {{ $t('team.member.passes.dialog.to') }}
                <span class="font-semibold text-charcoal">{{
                    memberName
                }}</span>
            </template>

            <div class="mt-5 space-y-4">
                <FormField
                    :label="$t('team.member.passes.fields.pass')"
                    required
                >
                    <template #default="{ id }">
                        <CustomDropdown
                            :id="id"
                            v-model="selectedPassId"
                            :items="passItems"
                            :placeholder="$t('ui.select.placeholder')"
                            :empty-text="$t('team.member.passes.no_options')"
                        />
                    </template>
                </FormField>

                <section
                    class="min-h-28 rounded-lg border border-primary/30 bg-primary-soft p-4"
                >
                    <h3 class="m-0 text-sm font-bold text-primary">
                        {{ $t('team.member.passes.dialog.entitlements') }}
                    </h3>
                    <p
                        v-if="!selectedPass"
                        class="mt-3 mb-0 text-sm text-muted"
                    >
                        {{ $t('team.member.passes.dialog.blank_entitlements') }}
                    </p>
                    <div
                        v-else-if="selectedPass.entitlements.length"
                        class="mt-2 divide-y divide-primary/15"
                    >
                        <div
                            v-for="entitlement in selectedPass.entitlements"
                            :key="entitlement.id"
                            class="flex flex-wrap items-center gap-2 py-3"
                        >
                            <span class="text-sm font-semibold text-charcoal">
                                {{ entitlement.name }}
                            </span>
                            <Tag
                                v-for="label in entitlement.labels"
                                :key="label.id"
                                :name="label.name"
                                :color="label.color"
                            />
                            <span
                                class="ml-auto text-sm font-semibold text-charcoal"
                            >
                                ×{{ entitlement.quantity }}
                            </span>
                        </div>
                    </div>
                    <p
                        v-else
                        class="mt-3 mb-0 text-sm text-muted"
                    >
                        {{ $t('team.member.passes.dialog.no_entitlements') }}
                    </p>
                </section>

                <p class="m-0 text-sm text-muted">
                    {{ $t('team.member.passes.dialog.one_at_a_time') }}
                </p>
            </div>
        </Popup>
    </section>
</template>
