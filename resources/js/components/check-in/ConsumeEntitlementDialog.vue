<script setup>
import { useForm } from '@inertiajs/vue3';
import { computed, nextTick, ref, watch } from 'vue';
import { Dialog } from '../ui/dialog';
import { EntitlementItemName } from '../ui/entitlement-labels';
import { FormField } from '../ui/form-field';
import { Input } from '../ui/input';
import { useFlashToast } from '../../composables/useFlashToast';

const props = defineProps({
    open: { type: Boolean, default: false },
    entitlement: { type: Object, default: null },
    endpoint: { type: String, default: '' },
});

const emit = defineEmits(['update:open']);
const form = useForm({ code: '' });
const codeInput = ref(null);
const needsUniqueCode = computed(() =>
    Boolean(props.entitlement?.needs_unique_code),
);
const canSubmit = computed(
    () =>
        Boolean(props.entitlement?.can_consume) &&
        !form.processing &&
        (!needsUniqueCode.value || form.code.trim().length > 0),
);
const { showFormError } = useFlashToast();

watch(
    () => [props.open, props.entitlement?.id],
    async ([open]) => {
        if (!open) return;
        form.clearErrors();
        form.code = '';
        if (needsUniqueCode.value) {
            await nextTick();
            codeInput.value?.$el?.focus();
        }
    },
);

const submit = () => {
    if (!canSubmit.value) return;
    form.post(props.endpoint, {
        preserveScroll: true,
        onSuccess: () => emit('update:open', false),
        onError: showFormError,
    });
};
</script>

<template>
    <Dialog
        :open="open"
        :title="$t('artists.check_in.consume_title')"
        :description="entitlement?.name"
        :confirm-label="$t('artists.check_in.consume')"
        :busy="form.processing"
        :confirm-disabled="!canSubmit"
        confirm-variant="primary"
        @update:open="$emit('update:open', $event)"
        @confirm="submit"
    >
        <template #description>
            <EntitlementItemName
                v-if="entitlement"
                :name="entitlement.name"
                :labels="entitlement.labels ?? []"
                stacked
            />
        </template>
        <div class="mt-4 flex flex-col gap-4">
            <p class="m-0 rounded-lg bg-page p-3 text-sm text-charcoal">
                <template
                    v-for="(part, index) in $t(
                        'check_in.from_your_stock',
                    ).split(':count')"
                    :key="index"
                >
                    <strong
                        v-if="index > 0"
                        class="font-bold"
                        >{{ entitlement?.own_stock ?? 0 }}</strong
                    >{{ part }}
                </template>
            </p>
            <p
                v-if="
                    entitlement?.consume_blocked_reason || form.errors.quantity
                "
                class="m-0 text-sm text-danger"
                role="alert"
            >
                {{ form.errors.quantity || entitlement.consume_blocked_reason }}
            </p>
            <FormField
                :label="
                    $t(
                        needsUniqueCode
                            ? 'artists.check_in.code'
                            : 'artists.check_in.code_optional',
                    )
                "
                :error="form.errors.code"
                :required="needsUniqueCode"
            >
                <template #default="{ id, invalid }">
                    <Input
                        :id="id"
                        ref="codeInput"
                        v-model="form.code"
                        :invalid="invalid"
                        :required="needsUniqueCode"
                        :disabled="form.processing"
                        maxlength="64"
                        autocomplete="off"
                        @keydown.enter.prevent="submit"
                    />
                </template>
            </FormField>
        </div>
    </Dialog>
</template>
