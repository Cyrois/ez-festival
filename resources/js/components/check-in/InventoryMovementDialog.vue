<script setup>
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { trans } from 'laravel-vue-i18n';
import { Dialog } from '../ui/dialog';
import { FormField } from '../ui/form-field';
import { Input } from '../ui/input';
import { Textarea } from '../ui/textarea';
import { Radio } from '../ui/radio';
import { CustomDropdown } from '../ui/custom-dropdown';
import { useFlashToast } from '../../composables/useFlashToast';

const props = defineProps({
    open: { type: Boolean, default: false },
    mode: { type: String, required: true },
    eventId: { type: Number, required: true },
    items: { type: Array, required: true },
    ownItems: { type: Array, required: true },
    locations: { type: Array, required: true },
    holder: { type: Object, default: null },
    ownPersonId: { type: Number, default: null },
});
const emit = defineEmits(['update:open', 'saved']);
const emptyMovement = () => ({
    location_id: '',
    person_id: '',
    entitlement_item_id: '',
    quantity: 1,
    destination: 'me',
    note: '',
});
const form = useForm(emptyMovement());
const confirm = ref(false);
const availability = ref([]);
const availabilityLoading = ref(false);
const receivers = ref([]);
const receiverPage = ref(1);
const receiverSearch = ref('');
const receiverHasMore = ref(false);
const receiverLoading = ref(false);
const receiver = ref(null);
const { showError, showFormError, showSuccess } = useFlashToast();
let receiverTimer;
let receiverRequest = 0;
let availabilityRequest = 0;
const pull = computed(() => props.mode === 'pull');
const moving = computed(() => props.mode === 'move');
const takeBack = computed(() => props.mode === 'take_back');
const sourceItems = computed(() =>
    takeBack.value ? (props.holder?.items ?? []) : props.ownItems,
);
const title = computed(() =>
    trans(
        pull.value
            ? 'inventory.pull'
            : takeBack.value
              ? 'inventory.take_back'
              : 'inventory.move',
    ),
);
const receiverEndpoint = computed(() =>
    moving.value
        ? '/check-in/inventory/move-receivers'
        : takeBack.value
          ? `/check-in/inventory/holders/${props.holder?.id}/receivers`
          : '/check-in/inventory/receivers',
);
const choices = computed(() =>
    pull.value
        ? props.items.filter((item) =>
              availability.value.some(
                  (balance) =>
                      balance.location_id === form.location_id &&
                      balance.entitlement_item_id === item.id &&
                      balance.balance > 0,
              ),
          )
        : sourceItems.value,
);
const itemOptions = computed(() =>
    choices.value.map((item) => ({
        value: item.id,
        title: item.name,
        ...(!pull.value
            ? {
                  description: trans('inventory.held_count', {
                      count: item.balance,
                  }),
              }
            : {}),
    })),
);
const locationOptions = computed(() =>
    props.locations
        .filter(
            (location) =>
                !pull.value ||
                availability.value.some(
                    (stock) =>
                        stock.location_id === location.id && stock.balance > 0,
                ),
        )
        .map((location) => ({ value: location.id, title: location.name })),
);
const available = computed(() =>
    Number(
        pull.value
            ? (availability.value.find(
                  (stock) =>
                      stock.location_id === form.location_id &&
                      stock.entitlement_item_id === form.entitlement_item_id,
              )?.balance ?? 0)
            : (sourceItems.value.find(
                  (item) => item.id === form.entitlement_item_id,
              )?.balance ?? 0),
    ),
);
const receiverOptions = computed(() => {
    const rows =
        receiver.value &&
        !receivers.value.some((person) => person.id === receiver.value.id)
            ? [receiver.value, ...receivers.value]
            : receivers.value;
    return rows.map((person) => ({
        value: person.id,
        title: person.name,
        avatar: person.name,
        group: person.is_manager
            ? trans('inventory.managers')
            : person.on_shift
              ? trans('inventory.on_shift')
              : person.in_group
                ? trans('inventory.in_group')
                : moving.value
                  ? trans('inventory.people')
                  : undefined,
    }));
});
const moveOptions = computed(() => [
    ...locationOptions.value
        .filter((item) =>
            item.title
                .toLocaleLowerCase()
                .includes(receiverSearch.value.trim().toLocaleLowerCase()),
        )
        .map((item) => ({
            ...item,
            value: `location:${item.value}`,
            group: trans('inventory.locations_storage'),
        })),
    ...receiverOptions.value.map((item) => ({
        ...item,
        value: `person:${item.value}`,
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
const destinationReady = computed(() =>
    pull.value
        ? form.location_id && form.person_id
        : moving.value
          ? form.location_id || form.person_id
          : takeBack.value
            ? form.destination === 'me'
                ? props.holder?.person_id !== props.ownPersonId
                : form.destination === 'location'
                  ? form.location_id
                  : form.person_id
            : form.person_id,
);
const ready = computed(
    () =>
        destinationReady.value &&
        form.entitlement_item_id &&
        Number.isInteger(Number(form.quantity)) &&
        Number(form.quantity) >= 1 &&
        Number(form.quantity) <= available.value,
);
const description = computed(() =>
    trans(
        pull.value
            ? 'inventory.confirm_pull'
            : takeBack.value
              ? 'inventory.confirm_take_back'
              : 'inventory.confirm_move',
        {
            quantity: form.quantity,
            item:
                choices.value.find(
                    (item) => item.id === form.entitlement_item_id,
                )?.name ?? '',
            location:
                props.locations.find(
                    (location) => location.id === form.location_id,
                )?.name ?? '',
            person: receiver.value?.name ?? '',
            holder: props.holder?.name ?? '',
            destination: moving.value
                ? form.person_id
                    ? (receiver.value?.name ?? '')
                    : (props.locations.find(
                          (location) => location.id === form.location_id,
                      )?.name ?? '')
                : form.destination === 'me'
                  ? trans('inventory.me')
                  : form.destination === 'location'
                    ? (props.locations.find(
                          (location) => location.id === form.location_id,
                      )?.name ?? '')
                    : (receiver.value?.name ?? ''),
        },
    ),
);
const loadReceivers = async (append = false) => {
    const requestId = ++receiverRequest;
    receiverLoading.value = true;
    const page = append ? receiverPage.value + 1 : 1;
    try {
        const response = await fetch(
            `${receiverEndpoint.value}?${new URLSearchParams({ search: receiverSearch.value, page })}`,
            { headers: { Accept: 'application/json' } },
        );
        if (!response.ok) throw new Error();
        const result = await response.json();
        if (requestId !== receiverRequest) return;
        receivers.value = append
            ? [...receivers.value, ...result.data]
            : result.data;
        receiverPage.value = page;
        receiverHasMore.value = result.links.next !== null;
    } catch {
        if (requestId === receiverRequest) showError(trans('errors.generic'));
    } finally {
        if (requestId === receiverRequest) receiverLoading.value = false;
    }
};
const searchReceivers = (value) => {
    receiverSearch.value = value;
    ++receiverRequest;
    window.clearTimeout(receiverTimer);
    receiverTimer = window.setTimeout(() => loadReceivers(), 250);
};
const loadAvailability = async () => {
    const requestId = ++availabilityRequest;
    availabilityLoading.value = true;
    try {
        const response = await fetch('/check-in/inventory/availability', {
            headers: { Accept: 'application/json' },
        });
        if (!response.ok) throw new Error();
        const result = await response.json();
        if (requestId === availabilityRequest) availability.value = result.data;
    } catch {
        if (requestId === availabilityRequest)
            showError(trans('errors.generic'));
    } finally {
        if (requestId === availabilityRequest)
            availabilityLoading.value = false;
    }
};
watch(
    () => props.open,
    (open) => {
        if (!open) {
            ++receiverRequest;
            ++availabilityRequest;
            window.clearTimeout(receiverTimer);
            return;
        }
        Object.assign(form, emptyMovement());
        form.clearErrors();
        confirm.value = false;
        availabilityLoading.value = false;
        receiverLoading.value = false;
        receiverHasMore.value = false;
        receiver.value = null;
        receivers.value = [];
        receiverSearch.value = '';
        availability.value = [];
        if (pull.value) loadAvailability();
        loadReceivers();
    },
);
watch(
    () => form.location_id,
    () => {
        if (pull.value) form.entitlement_item_id = '';
    },
);
watch(
    () => form.entitlement_item_id,
    () => {
        if (!pull.value) {
            form.quantity =
                takeBack.value && form.entitlement_item_id
                    ? available.value
                    : 1;
        }
    },
);
watch(
    () => form.destination,
    () => {
        form.location_id = '';
        form.person_id = '';
        receiver.value = null;
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
    ++availabilityRequest;
    window.clearTimeout(receiverTimer);
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
            showSuccess(
                trans(
                    pull.value
                        ? 'inventory.toast.pulled'
                        : takeBack.value
                          ? 'inventory.toast.taken_back'
                          : 'inventory.toast.moved',
                ),
            );
            emit('update:open', false);
            emit('saved');
        },
        onError: (errors) => {
            confirm.value = false;
            showFormError(errors);
            if (pull.value) loadAvailability();
            if (errors.person_id) {
                form.person_id = '';
                receiver.value = null;
                loadReceivers();
            }
        },
    };
    if (pull.value)
        form.post(`/events/${props.eventId}/check-in/inventory/pulls`, options);
    else if (moving.value && (form.location_id || receiver.value?.is_manager))
        form.put(
            `/events/${props.eventId}/check-in/inventory/returns`,
            options,
        );
    else if (takeBack.value)
        form.post(
            `/events/${props.eventId}/check-in/inventory/holders/${props.holder.id}/take-backs`,
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
        :title="title"
        :description="
            confirm
                ? description
                : takeBack
                  ? $t('inventory.take_back_description', {
                        name: holder?.name ?? '',
                    })
                  : moving
                    ? $t('inventory.move_description')
                    : ''
        "
        :confirm-label="$t(confirm ? 'inventory.confirm' : 'inventory.review')"
        :confirm-disabled="!ready || availabilityLoading"
        :busy="form.processing"
        confirm-variant="primary"
        focus-trap
        @update:open="$emit('update:open', $event)"
        @confirm="submit"
    >
        <div
            v-if="!confirm"
            class="mt-4 space-y-4"
        >
            <FormField
                v-if="pull"
                v-slot="{ id, invalid }"
                :label="$t('inventory.columns.location')"
                :error="form.errors.location_id"
                required
            >
                <CustomDropdown
                    :id="id"
                    v-model="form.location_id"
                    :items="locationOptions"
                    :invalid="invalid"
                    :disabled="form.processing || availabilityLoading"
                    :placeholder="$t('inventory.choose_location')"
                />
            </FormField>
            <FormField
                v-if="pull"
                v-slot="{ id, invalid }"
                :label="$t('inventory.columns.person')"
                :error="form.errors.person_id"
                required
            >
                <CustomDropdown
                    :id="id"
                    v-model="form.person_id"
                    :items="receiverOptions"
                    :invalid="invalid"
                    :disabled="form.processing"
                    :loading="receiverLoading"
                    :has-more="receiverHasMore"
                    :placeholder="$t('inventory.choose_person')"
                    :empty-text="$t('inventory.no_receivers')"
                    remote
                    @search="searchReceivers"
                    @load-more="loadReceivers(true)"
                />
            </FormField>
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
                    :disabled="form.processing || (pull && !form.location_id)"
                    :placeholder="$t('inventory.choose_item')"
                />
            </FormField>
            <p
                class="text-sm text-muted"
                aria-live="polite"
            >
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
                v-if="moving"
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
            <fieldset
                v-if="takeBack"
                class="flex flex-col gap-3"
            >
                <legend class="mb-2 text-sm font-medium">
                    {{ $t('inventory.where_it_goes') }}
                </legend>
                <Radio
                    v-model="form.destination"
                    value="me"
                    name="inventory-destination"
                    :label="$t('inventory.me')"
                    :disabled="
                        form.processing || holder?.person_id === ownPersonId
                    "
                />
                <Radio
                    v-model="form.destination"
                    value="location"
                    name="inventory-destination"
                    :label="$t('inventory.a_location')"
                    :disabled="form.processing"
                />
                <Radio
                    v-model="form.destination"
                    value="person"
                    name="inventory-destination"
                    :label="$t('inventory.another_person')"
                    :disabled="form.processing"
                />
                <p
                    v-if="form.errors.destination"
                    class="text-sm text-danger"
                >
                    {{ form.errors.destination }}
                </p>
            </fieldset>
            <FormField
                v-if="takeBack && form.destination === 'location'"
                v-slot="{ id, invalid }"
                :label="$t('inventory.columns.location')"
                :error="form.errors.location_id"
                required
            >
                <CustomDropdown
                    :id="id"
                    v-model="form.location_id"
                    :items="locationOptions"
                    :invalid="invalid"
                    :disabled="form.processing"
                    :placeholder="$t('inventory.choose_location')"
                />
            </FormField>
            <FormField
                v-if="takeBack && form.destination === 'person'"
                v-slot="{ id, invalid }"
                :label="$t('inventory.columns.person')"
                :error="form.errors.person_id"
                required
            >
                <CustomDropdown
                    :id="id"
                    v-model="form.person_id"
                    :items="receiverOptions"
                    :invalid="invalid"
                    :disabled="form.processing"
                    :loading="receiverLoading"
                    :has-more="receiverHasMore"
                    :placeholder="$t('inventory.choose_person')"
                    :empty-text="$t('inventory.no_receivers')"
                    remote
                    @search="searchReceivers"
                    @load-more="loadReceivers(true)"
                />
            </FormField>
            <FormField
                v-if="takeBack"
                v-slot="{ id, invalid }"
                :label="$t('inventory.note_optional')"
                :error="form.errors.note"
            >
                <Textarea
                    :id="id"
                    v-model="form.note"
                    :invalid="invalid"
                    :disabled="form.processing"
                    maxlength="255"
                />
            </FormField>
        </div>
    </Dialog>
</template>
