<script setup>
import DataTablesCore from 'datatables.net-dt';
import DataTablesVue from 'datatables.net-vue3';
import { trans } from 'laravel-vue-i18n';
import { computed, ref } from 'vue';
import { cn } from '../../../lib/utils';

defineOptions({ inheritAttrs: false });

DataTablesVue.use(DataTablesCore);

const props = defineProps({
    ajax: {
        type: [String, Object, Function],
        default: undefined,
    },
    columns: {
        type: Array,
        default: undefined,
    },
    data: {
        type: Array,
        default: undefined,
    },
    options: {
        type: Object,
        default: () => ({}),
    },
    class: {
        type: [String, Object, Array],
        default: '',
    },
});

const dataTable = ref(null);
const classes = computed(() =>
    cn('w-full border-collapse text-left text-sm', props.class),
);
const options = computed(() => ({
    autoWidth: false,
    processing: true,
    ...props.options,
    language: {
        emptyTable: trans('data_table.empty'),
        info: trans('data_table.info'),
        infoEmpty: trans('data_table.info_empty'),
        infoFiltered: trans('data_table.info_filtered'),
        lengthMenu: trans('data_table.length_menu'),
        loadingRecords: trans('data_table.loading'),
        processing: trans('data_table.processing'),
        search: trans('data_table.search'),
        searchPlaceholder: trans('data_table.search_placeholder'),
        zeroRecords: trans('data_table.zero_records'),
        ...props.options.language,
        paginate: {
            first: trans('data_table.pagination.first'),
            last: trans('data_table.pagination.last'),
            next: trans('data_table.pagination.next'),
            previous: trans('data_table.pagination.previous'),
            ...props.options.language?.paginate,
        },
    },
}));

defineExpose({
    dataTable,
});
</script>

<template>
    <DataTablesVue
        ref="dataTable"
        v-bind="$attrs"
        :ajax="ajax"
        :columns="columns"
        :data="data"
        :options="options"
        :class="classes"
    >
        <template
            v-for="(_, slotName) in $slots"
            #[slotName]="slotProps"
        >
            <slot
                :name="slotName"
                v-bind="slotProps || {}"
            />
        </template>
    </DataTablesVue>
</template>
