<script setup>
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { trans } from 'laravel-vue-i18n';
import { Dialog } from '../ui/dialog';
import { Button } from '../ui/button';
import { Input } from '../ui/input';
import { Textarea } from '../ui/textarea';
import { Radio } from '../ui/radio';
import { FormField } from '../ui/form-field';
import { CustomDropdown } from '../ui/custom-dropdown';
import { SegmentedControl } from '../ui/segmented-control';
import { EntitlementItemName } from '../ui/entitlement-labels';
import { useFlashToast } from '../../composables/useFlashToast';
import InventoryPicker from './InventoryPicker.vue';

const props = defineProps({
    open: { type: Boolean, default: false },
    mode: { type: String, required: true },
    eventId: { type: Number, required: true },
    person: { type: Object, default: null },
    scope: { type: Object, default: () => ({}) },
    canManage: { type: Boolean, default: false },
    canReceive: { type: Boolean, default: false },
    ownPersonId: { type: Number, default: null },
    ownItems: { type: Array, required: true },
    items: { type: Array, required: true },
    locations: { type: Array, required: true },
});
const emit = defineEmits(['update:open', 'saved']);
const { showError, showFormError, showSuccess } = useFlashToast();
const emptyLine = () => ({
    entitlement_item_id: '',
    quantity: 1,
    location_id: '',
});
const emptyForm = () => ({
    person_id: '',
    receiver_id: '',
    location_id: '',
    destination: 'me',
    direction: 'from_location',
    note: '',
    items: [],
});
const form = useForm(emptyForm());
const loading = ref(false);
const failed = ref(false);
const sourceItems = ref([]);
const availability = ref([]);
let sequence = 0;
const takeBack = computed(() => props.mode === 'take_back');
const adjust = computed(() => props.mode === 'adjust');
const fromLocation = computed(
    () => adjust.value && form.direction === 'from_location',
);
const title = computed(() =>
    trans(
        takeBack.value
            ? 'inventory.take_back'
            : adjust.value
              ? 'inventory.adjust'
              : 'inventory.give',
    ),
);
const noteVisible = computed(
    () => takeBack.value || (adjust.value && !fromLocation.value),
);
const locationOptions = computed(() =>
    props.locations.map((location) => ({
        value: location.id,
        title: location.name,
    })),
);
const sourceOptions = computed(() => [
    { value: '', title: trans('inventory.my_stock') },
    ...(props.canManage ? locationOptions.value : []),
]);
const itemOptions = computed(() =>
    (takeBack.value || (adjust.value && !fromLocation.value)
        ? sourceItems.value
        : props.items
    ).map((item) => ({
        value: item.id,
        title: item.name,
        labels: item.labels ?? [],
    })),
);
const available = (line) => {
    if (takeBack.value || (adjust.value && !fromLocation.value))
        return (
            sourceItems.value.find(
                (item) => item.id === line.entitlement_item_id,
            )?.balance ?? 0
        );
    const location = adjust.value ? form.location_id : line.location_id;
    if (location)
        return (
            availability.value.find(
                (item) =>
                    item.location_id === location &&
                    item.entitlement_item_id === line.entitlement_item_id,
            )?.balance ?? 0
        );
    return (
        props.ownItems.find((item) => item.id === line.entitlement_item_id)
            ?.balance ?? 0
    );
};
const positiveLines = computed(() =>
    form.items.filter((line) => Number(line.quantity) > 0),
);
const ready = computed(() => {
    if (
        loading.value ||
        failed.value ||
        !form.person_id ||
        !form.items.length ||
        !positiveLines.value.length
    )
        return false;
    if (
        new Set(form.items.map((line) => line.entitlement_item_id)).size !==
        form.items.length
    )
        return false;
    if (adjust.value && !form.location_id) return false;
    if (
        takeBack.value &&
        ((form.destination === 'location' && !form.location_id) ||
            (form.destination === 'person' && !form.receiver_id))
    )
        return false;
    if (
        takeBack.value &&
        form.destination === 'me' &&
        (!props.canReceive || props.person?.id === props.ownPersonId)
    )
        return false;
    return form.items.every(
        (line) =>
            line.entitlement_item_id &&
            String(line.quantity).trim() !== '' &&
            Number.isInteger(Number(line.quantity)) &&
            Number(line.quantity) >= (takeBack.value ? 0 : 1) &&
            Number(line.quantity) <= Math.min(available(line), 2147483647),
    );
});
const summary = computed(() =>
    trans('inventory.batch_summary', {
        items: positiveLines.value.length,
        count: positiveLines.value.reduce(
            (sum, line) => sum + Number(line.quantity),
            0,
        ),
    }),
);
const loadStock = async () => {
    const request = ++sequence;
    loading.value = true;
    failed.value = false;
    try {
        const needsPerson =
            takeBack.value || (adjust.value && !fromLocation.value);
        if (needsPerson && !form.person_id) {
            sourceItems.value = [];
            return;
        }
        const url = needsPerson
            ? `/check-in/inventory/${takeBack.value ? 'holdings/stock' : 'adjustment-stock'}?${new URLSearchParams({ ...(takeBack.value ? props.scope : { direction: form.direction }), person_id: form.person_id })}`
            : props.canManage
              ? '/check-in/inventory/availability'
              : null;
        if (!url) return;
        const response = await fetch(url, {
            headers: { Accept: 'application/json' },
        });
        if (!response.ok) throw new Error();
        const result = await response.json();
        if (request !== sequence) return;
        if (needsPerson) {
            sourceItems.value = result.data;
            if (takeBack.value)
                form.items = result.data.map((item) => ({
                    entitlement_item_id: item.id,
                    quantity: item.balance,
                    location_id: '',
                }));
        } else availability.value = result.data;
    } catch {
        if (request === sequence) failed.value = true;
    } finally {
        if (request === sequence) loading.value = false;
    }
};
watch(
    () => props.open,
    (open) => {
        ++sequence;
        if (!open) return;
        Object.assign(form, emptyForm());
        form.clearErrors();
        form.person_id = props.person?.id ?? '';
        form.destination =
            props.canReceive && props.person?.id !== props.ownPersonId
                ? 'me'
                : 'location';
        form.items = takeBack.value ? [] : [emptyLine()];
        sourceItems.value = [];
        availability.value = [];
        loadStock();
    },
);
watch(
    () => [form.direction, form.person_id],
    () => {
        if (!props.open || !adjust.value) return;
        form.items = [emptyLine()];
        form.note = '';
        loadStock();
    },
);
watch(
    () => form.direction,
    () => {
        if (adjust.value) form.person_id = '';
    },
);
const save = () => {
    if (!ready.value || form.processing) return;
    form.transform((data) => ({
        ...(adjust.value
            ? {
                  direction: data.direction,
                  person_id: data.person_id,
                  location_id: data.location_id,
              }
            : { ...props.scope, person_id: data.person_id }),
        ...(takeBack.value
            ? {
                  destination: data.destination,
                  ...(data.destination === 'location'
                      ? { location_id: data.location_id }
                      : data.destination === 'person'
                        ? { receiver_id: data.receiver_id }
                        : {}),
              }
            : {}),
        ...(noteVisible.value ? { note: data.note } : {}),
        items: data.items.map((line) => ({
            entitlement_item_id: line.entitlement_item_id,
            quantity: Number(line.quantity),
            ...(!takeBack.value && !adjust.value && line.location_id
                ? { location_id: line.location_id }
                : {}),
        })),
    })).post(
        `/events/${props.eventId}/check-in/inventory/${takeBack.value ? 'take-backs' : adjust.value ? 'adjustments' : 'gives'}`,
        {
            preserveScroll: true,
            onError: (errors) => showFormError(errors),
            onSuccess: () => {
                showSuccess(trans('inventory.toast.moved'));
                emit('update:open', false);
                emit('saved');
            },
            onException: () => showError(trans('errors.generic')),
        },
    );
};
onBeforeUnmount(() => {
    ++sequence;
});
</script>

<template>
    <Dialog
        :open="open"
        :title="title"
        :confirm-label="title"
        :confirm-disabled="!ready"
        :busy="form.processing"
        confirm-variant="primary"
        class="max-w-3xl"
        sectioned
        focus-trap
        role="dialog"
        @confirm="save"
        @update:open="emit('update:open', $event)"
    >
        <p
            v-if="person"
            class="mb-4 font-semibold"
        >
            {{ person.name }}
        </p>
        <div class="space-y-4">
            <SegmentedControl
                v-if="adjust"
                v-model="form.direction"
                :aria-label="$t('inventory.direction')"
                :options="[
                    {
                        value: 'from_location',
                        label: $t('inventory.from_location'),
                    },
                    {
                        value: 'to_location',
                        label: $t('inventory.to_location'),
                    },
                ]"
                :disabled="form.processing"
            />
            <template v-if="adjust">
                <FormField
                    :label="$t('inventory.columns.location')"
                    :error="form.errors.location_id"
                    required
                >
                    <CustomDropdown
                        v-model="form.location_id"
                        :items="locationOptions"
                        :aria-label="$t('inventory.columns.location')"
                        :placeholder="$t('inventory.choose_location')"
                        :disabled="form.processing"
                    />
                </FormField>
                <FormField
                    :label="$t('inventory.columns.person')"
                    :error="form.errors.person_id"
                    required
                >
                    <InventoryPicker
                        v-if="open"
                        :key="form.direction"
                        v-model="form.person_id"
                        endpoint="/check-in/inventory/adjustment-people"
                        :parameters="{ direction: form.direction }"
                        :label="$t('inventory.choose_person')"
                        :disabled="form.processing"
                    />
                </FormField>
            </template>
            <p
                v-if="loading"
                role="status"
                class="text-sm text-muted"
            >
                {{ $t('data_table.loading') }}
            </p>
            <p
                v-if="failed"
                class="text-sm text-danger"
                role="alert"
            >
                {{ $t('inventory.holdings_error') }}
                <Button
                    variant="ghost"
                    @click="loadStock"
                    >{{ $t('inventory.history.retry') }}</Button
                >
            </p>
            <p
                v-if="form.errors.items"
                class="text-sm text-danger"
                role="alert"
            >
                {{ form.errors.items }}
            </p>
            <div
                v-for="(line, index) in form.items"
                :key="index"
                class="grid gap-3 rounded-lg border border-line p-3 md:grid-cols-3"
            >
                <FormField
                    :label="$t('inventory.columns.item')"
                    :error="form.errors[`items.${index}.entitlement_item_id`]"
                    :class="(adjust || takeBack) && 'md:col-span-2'"
                    required
                >
                    <EntitlementItemName
                        v-if="takeBack"
                        :name="
                            sourceItems.find(
                                (item) => item.id === line.entitlement_item_id,
                            )?.name ?? ''
                        "
                        :labels="
                            sourceItems.find(
                                (item) => item.id === line.entitlement_item_id,
                            )?.labels ?? []
                        "
                    />
                    <CustomDropdown
                        v-else
                        v-model="line.entitlement_item_id"
                        :items="
                            itemOptions.filter(
                                (item) =>
                                    item.value === line.entitlement_item_id ||
                                    !form.items.some(
                                        (other) =>
                                            other.entitlement_item_id ===
                                            item.value,
                                    ),
                            )
                        "
                        :aria-label="$t('inventory.columns.item')"
                        :placeholder="$t('inventory.choose_item')"
                        :disabled="form.processing"
                    />
                </FormField>
                <FormField
                    :label="$t('inventory.columns.quantity')"
                    :error="form.errors[`items.${index}.quantity`]"
                    :hint="
                        $t('inventory.available', { count: available(line) })
                    "
                    required
                >
                    <template #default="{ id, invalid }">
                        <Input
                            :id="id"
                            v-model="line.quantity"
                            type="number"
                            :min="takeBack ? 0 : 1"
                            :max="Math.min(available(line), 2147483647)"
                            step="1"
                            :invalid="invalid"
                            :disabled="form.processing"
                        />
                    </template>
                </FormField>
                <FormField
                    v-if="!adjust && !takeBack"
                    :label="$t('inventory.from')"
                    :error="form.errors[`items.${index}.location_id`]"
                >
                    <CustomDropdown
                        v-if="canManage"
                        v-model="line.location_id"
                        :items="sourceOptions"
                        :aria-label="$t('inventory.from')"
                        :disabled="form.processing"
                    />
                    <span v-else>{{ $t('inventory.my_stock') }}</span>
                </FormField>
                <Button
                    v-if="!takeBack"
                    variant="ghost"
                    :disabled="form.processing || form.items.length === 1"
                    @click="form.items.splice(index, 1)"
                    >{{ $t('inventory.remove_item') }}</Button
                >
            </div>
            <Button
                v-if="!takeBack"
                variant="outline"
                :disabled="
                    form.processing || form.items.length >= itemOptions.length
                "
                @click="form.items.push(emptyLine())"
                >{{ $t('inventory.add_item') }}</Button
            >
            <template v-if="takeBack">
                <FormField
                    :label="$t('inventory.where_it_goes')"
                    :error="form.errors.destination"
                    required
                >
                    <Radio
                        v-model="form.destination"
                        name="inventory-batch-destination"
                        value="me"
                        :label="$t('inventory.me')"
                        :disabled="
                            form.processing ||
                            !canReceive ||
                            person?.id === ownPersonId
                        "
                    />
                    <Radio
                        v-model="form.destination"
                        name="inventory-batch-destination"
                        value="location"
                        :label="$t('inventory.a_location')"
                        :disabled="form.processing"
                    />
                    <Radio
                        v-model="form.destination"
                        name="inventory-batch-destination"
                        value="person"
                        :label="$t('inventory.another_person')"
                        :disabled="form.processing"
                    />
                </FormField>
                <FormField
                    v-if="form.destination === 'location'"
                    :error="form.errors.location_id"
                >
                    <CustomDropdown
                        v-model="form.location_id"
                        :items="locationOptions"
                        :aria-label="$t('inventory.choose_location')"
                        :placeholder="$t('inventory.choose_location')"
                        :disabled="form.processing"
                    />
                </FormField>
                <FormField
                    v-if="form.destination === 'person'"
                    :error="form.errors.receiver_id"
                >
                    <InventoryPicker
                        v-if="open"
                        v-model="form.receiver_id"
                        endpoint="/check-in/inventory/holdings/receivers"
                        :parameters="{ ...scope, person_id: person.id }"
                        :label="$t('inventory.choose_person')"
                        :disabled="form.processing"
                    />
                </FormField>
            </template>
            <FormField
                v-if="noteVisible"
                :label="$t('inventory.note_optional')"
                :error="form.errors.note"
            >
                <template #default="{ id, invalid }">
                    <Textarea
                        :id="id"
                        v-model="form.note"
                        :invalid="invalid"
                        :disabled="form.processing"
                        maxlength="255"
                    />
                </template>
            </FormField>
            <p class="text-sm text-muted">{{ summary }}</p>
        </div>
    </Dialog>
</template>
