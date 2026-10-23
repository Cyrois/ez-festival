<script setup>
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import { getActiveLanguage, trans } from 'laravel-vue-i18n';
import { CustomDropdown } from '../ui/custom-dropdown';
import { Button } from '../ui/button';

const props = defineProps({
    modelValue: { type: [String, Number], default: '' },
    endpoint: { type: String, required: true },
    parameters: { type: Object, default: () => ({}) },
    shift: { type: Boolean, default: false },
    disabled: { type: Boolean, default: false },
    invalid: { type: Boolean, default: false },
    label: { type: String, required: true },
});
const emit = defineEmits(['update:modelValue']);
const rows = ref([]);
const selected = ref(null);
const loading = ref(false);
const failed = ref(false);
const hasMore = ref(false);
const page = ref(1);
const search = ref('');
let sequence = 0;
let timer;
const load = async (append = false) => {
    const request = ++sequence;
    loading.value = true;
    failed.value = false;
    const nextPage = append ? page.value + 1 : 1;
    try {
        const response = await fetch(
            `${props.endpoint}?${new URLSearchParams({ ...props.parameters, search: search.value, page: nextPage })}`,
            { headers: { Accept: 'application/json' } },
        );
        if (!response.ok) throw new Error();
        const result = await response.json();
        if (request !== sequence) return;
        rows.value = append ? [...rows.value, ...result.data] : result.data;
        page.value = nextPage;
        hasMore.value = Boolean(result.links?.next);
    } catch {
        if (request === sequence) failed.value = true;
    } finally {
        if (request === sequence) loading.value = false;
    }
};
// Shift timestamps are event-local wall clocks. Format them without converting zones.
const formatTime = (value) =>
    new Intl.DateTimeFormat(getActiveLanguage(), {
        dateStyle: 'medium',
        timeStyle: 'short',
        timeZone: 'UTC',
    }).format(new Date(`${value.replace(' ', 'T')}Z`));
const options = computed(() => {
    const catalogue =
        selected.value &&
        !rows.value.some((row) => row.id === selected.value.id)
            ? [selected.value, ...rows.value]
            : rows.value;
    return catalogue.map((row) => ({
        value: row.id,
        title: props.shift
            ? trans('inventory.shift_option', {
                  name: row.name || trans('team.scheduling.unnamed_shift'),
                  location: row.location,
                  start: formatTime(row.starts_at),
                  end: formatTime(row.ends_at),
              })
            : row.name,
        ...(props.shift
            ? {
                  group: trans(
                      [
                          'inventory.in_progress',
                          'inventory.upcoming',
                          'inventory.past',
                      ][row.period],
                  ),
              }
            : {}),
    }));
});
const choose = (value) => {
    selected.value = rows.value.find((row) => row.id === value) ?? null;
    emit('update:modelValue', value);
};
const find = (value) => {
    search.value = value;
    ++sequence;
    clearTimeout(timer);
    timer = setTimeout(() => load(), 250);
};
watch(
    () => [props.endpoint, JSON.stringify(props.parameters)],
    () => {
        search.value = '';
        rows.value = [];
        selected.value = null;
        load();
    },
    { immediate: true },
);
onBeforeUnmount(() => {
    ++sequence;
    clearTimeout(timer);
});
</script>

<template>
    <div>
        <CustomDropdown
            :model-value="modelValue"
            :items="options"
            :aria-label="label"
            :placeholder="label"
            :loading="loading"
            :has-more="hasMore"
            :disabled="disabled"
            :invalid="invalid"
            remote
            @update:model-value="choose"
            @search="find"
            @load-more="load(true)"
        />
        <p
            v-if="failed"
            class="mt-2 text-sm text-danger"
            role="alert"
        >
            {{ $t('inventory.options_error') }}
            <Button
                variant="ghost"
                @click="load()"
                >{{ $t('inventory.history.retry') }}</Button
            >
        </p>
    </div>
</template>
