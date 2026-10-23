<script setup>
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { trans } from 'laravel-vue-i18n';
import { Dialog } from '../ui/dialog';
import { EntitlementItemName } from '../ui/entitlement-labels';
import { FormField } from '../ui/form-field';
import { Input } from '../ui/input';
import { CustomDropdown } from '../ui/custom-dropdown';
import { useFlashToast } from '../../composables/useFlashToast';

const props = defineProps({
    open: { type: Boolean, default: false },
    eventId: { type: Number, required: true },
    ownItems: { type: Array, required: true },
    locations: { type: Array, required: true },
});
const emit = defineEmits(['update:open', 'saved']);
const emptyMovement = () => ({
    location_id: '',
    person_id: '',
    entitlement_item_id: '',
    quantity: 1,
});
const form = useForm(emptyMovement());
const confirm = ref(false);
const receivers = ref([]);
const receiver = ref(null);
const receiverPage = ref(1);
const receiverSearch = ref('');
const receiverHasMore = ref(false);
const receiverLoading = ref(false);
const { showError, showFormError, showSuccess } = useFlashToast();
let receiverRequest = 0;
let receiverTimer;
const selectedItem = computed(() =>
    props.ownItems.find((item) => item.id === form.entitlement_item_id),
);
const available = computed(() => selectedItem.value?.balance ?? 0);
const itemOptions = computed(() =>
    props.ownItems.map((item) => ({
        value: item.id,
        title: item.name,
        labels: item.labels ?? [],
        description: trans('inventory.held_count', { count: item.balance }),
    })),
);
const moveOptions = computed(() => [
    ...props.locations
        .filter((item) =>
            item.name
                .toLocaleLowerCase()
                .includes(receiverSearch.value.trim().toLocaleLowerCase()),
        )
        .map((item) => ({
            value: `location:${item.id}`,
            title: item.name,
            group: trans('inventory.locations_storage'),
        })),
    ...receivers.value.map((person) => ({
        value: `person:${person.id}`,
        title: person.name,
        group: trans(
            person.is_manager
                ? 'inventory.managers'
                : person.on_shift
                  ? 'inventory.on_shift'
                  : person.in_group
                    ? 'inventory.in_group'
                    : 'inventory.people',
        ),
    })),
]);
const moveTarget = computed({
    get: () =>
        form.person_id
            ? `person:${form.person_id}`
            : form.location_id
              ? `location:${form.location_id}`
              : '',
    set: (value) => {
        const [kind, id] = String(value).split(':');
        form.location_id = kind === 'location' ? Number(id) : '';
        form.person_id = kind === 'person' ? Number(id) : '';
    },
});
const ready = computed(
    () =>
        (form.location_id || form.person_id) &&
        form.entitlement_item_id &&
        Number.isInteger(Number(form.quantity)) &&
        Number(form.quantity) >= 1 &&
        Number(form.quantity) <= available.value,
);
const description = computed(() =>
    trans('inventory.confirm_move', {
        quantity: form.quantity,
        item: selectedItem.value?.name ?? '',
        destination: form.person_id
            ? (receiver.value?.name ?? '')
            : (props.locations.find((item) => item.id === form.location_id)
                  ?.name ?? ''),
    }),
);
const loadReceivers = async (append = false) => {
    const request = ++receiverRequest;
    receiverLoading.value = true;
    const page = append ? receiverPage.value + 1 : 1;
    try {
        const response = await fetch(
            `/check-in/inventory/move-receivers?${new URLSearchParams({ search: receiverSearch.value, page })}`,
            { headers: { Accept: 'application/json' } },
        );
        if (!response.ok) throw new Error();
        const result = await response.json();
        if (request !== receiverRequest) return;
        receivers.value = append
            ? [...receivers.value, ...result.data]
            : result.data;
        receiverPage.value = page;
        receiverHasMore.value = Boolean(result.links.next);
    } catch {
        if (request === receiverRequest) showError(trans('errors.generic'));
    } finally {
        if (request === receiverRequest) receiverLoading.value = false;
    }
};
const searchReceivers = (value) => {
    receiverSearch.value = value;
    ++receiverRequest;
    clearTimeout(receiverTimer);
    receiverTimer = setTimeout(() => loadReceivers(), 250);
};
watch(
    () => props.open,
    (open) => {
        ++receiverRequest;
        clearTimeout(receiverTimer);
        if (!open) return;
        Object.assign(form, emptyMovement());
        form.clearErrors();
        confirm.value = false;
        receiverLoading.value = false;
        receiverHasMore.value = false;
        receiver.value = null;
        receivers.value = [];
        receiverSearch.value = '';
        loadReceivers();
    },
);
watch(
    () => form.entitlement_item_id,
    () => {
        form.quantity = 1;
    },
);
watch(
    () => form.person_id,
    (id) => {
        receiver.value =
            receivers.value.find((person) => person.id === id) ??
            receiver.value;
    },
);
onBeforeUnmount(() => {
    ++receiverRequest;
    clearTimeout(receiverTimer);
});
const submit = () => {
    if (!ready.value || form.processing) return;
    if (!confirm.value) {
        confirm.value = true;
        return;
    }
    const options = {
        preserveScroll: true,
        onSuccess: () => {
            showSuccess(trans('inventory.toast.moved'));
            emit('update:open', false);
            emit('saved');
        },
        onError: (errors) => {
            confirm.value = false;
            showFormError(errors);
            if (errors.person_id) {
                form.person_id = '';
                receiver.value = null;
                loadReceivers();
            }
        },
    };
    if (form.location_id || receiver.value?.is_manager)
        form.put(
            `/events/${props.eventId}/check-in/inventory/returns`,
            options,
        );
    else
        form.post(
            `/events/${props.eventId}/check-in/inventory/transfers`,
            options,
        );
};
</script>

<template>
    <Dialog
        :open="open"
        :title="$t('inventory.move')"
        :description="confirm ? description : $t('inventory.move_description')"
        :confirm-label="$t(confirm ? 'inventory.confirm' : 'inventory.review')"
        :confirm-disabled="!ready"
        :busy="form.processing"
        confirm-variant="primary"
        focus-trap
        @update:open="emit('update:open', $event)"
        @confirm="submit"
    >
        <EntitlementItemName
            v-if="confirm && selectedItem"
            :name="selectedItem.name"
            :labels="selectedItem.labels ?? []"
            class="mt-3"
        />
        <div
            v-if="!confirm"
            class="mt-4 space-y-4"
        >
            <FormField
                v-slot="{ id, invalid }"
                :label="$t('inventory.columns.item')"
                :error="form.errors.entitlement_item_id"
                required
            >
                <CustomDropdown
                    :id="id"
                    v-model="form.entitlement_item_id"
                    :items="itemOptions"
                    :invalid="invalid"
                    :disabled="form.processing"
                    :placeholder="$t('inventory.choose_item')"
                />
            </FormField>
            <p class="text-sm text-muted">
                {{ $t('inventory.available', { count: available }) }}
            </p>
            <FormField
                v-slot="{ id, invalid }"
                :label="$t('inventory.columns.quantity')"
                :error="form.errors.quantity"
                required
            >
                <Input
                    :id="id"
                    v-model="form.quantity"
                    :invalid="invalid"
                    :disabled="form.processing"
                    type="number"
                    min="1"
                    :max="available"
                    step="1"
                />
            </FormField>
            <FormField
                v-slot="{ id, invalid }"
                :label="$t('inventory.move_to')"
                :error="form.errors.person_id || form.errors.location_id"
                required
            >
                <CustomDropdown
                    :id="id"
                    v-model="moveTarget"
                    :items="moveOptions"
                    :invalid="invalid"
                    :disabled="form.processing"
                    :loading="receiverLoading"
                    :has-more="receiverHasMore"
                    :placeholder="$t('inventory.choose_move_target')"
                    remote
                    @search="searchReceivers"
                    @load-more="loadReceivers(true)"
                />
            </FormField>
        </div>
    </Dialog>
</template>
