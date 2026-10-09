<script setup>
import DataTablesCore from 'datatables.net-dt';
import DataTablesVue from 'datatables.net-vue3';
import {
    currentLocale,
    getActiveLanguage,
    isLoaded,
    loadLanguageAsync,
    trans,
} from 'laravel-vue-i18n';
import { computed, onMounted, ref } from 'vue';
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
const ready = ref(isLoaded(getActiveLanguage()));
const classes = computed(() =>
    cn('dt-stacked w-full border-collapse text-left text-sm', props.class),
);
// DataTables reads options only when it mounts. Locale changes remount the
// table below; callers should not expect other option changes to be reactive.
const options = computed(() => ({
    autoWidth: false,
    processing: props.ajax !== undefined || props.options.serverSide === true,
    ...props.options,
    layout: {
        bottomEnd: { paging: { firstLast: false } },
        ...props.options.layout,
    },
    drawCallback: function (...args) {
        const table = this.api();
        const labels = table
            .columns()
            .header()
            .toArray()
            .map((header) =>
                (
                    header.querySelector('.dt-column-title') || header
                ).textContent.trim(),
            );

        // Use the actual column index, including hidden columns. Leave cell
        // contents intact so Vue slots and their interactive controls still work.
        table.cells({ page: 'current' }).every(function () {
            this.node().dataset.label = labels[this.index().column];
            this.node().setAttribute('role', 'cell');
        });
        table
            .rows({ page: 'current' })
            .nodes()
            .toArray()
            .forEach((row) => {
                row.setAttribute('role', 'row');
            });

        props.options.drawCallback?.apply(this, args);
    },
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

onMounted(async () => {
    if (!ready.value) {
        await loadLanguageAsync(getActiveLanguage());
        ready.value = true;
    }
});

const api = () => {
    const instance = dataTable.value?.dt;

    return instance?.value || instance;
};

const search = (value) => {
    api()?.search(value).draw();
};

// Named row filter on raw row data; pass null to clear it.
const filterRows = (name, predicate) => {
    api()
        ?.search.fixed(
            name,
            predicate ? (_, rowData) => predicate(rowData) : null,
        )
        .draw();
};

const reload = (resetPaging = true) => {
    api()?.ajax.reload(null, resetPaging);
};

defineExpose({
    dataTable,
    filterRows,
    reload,
    search,
});
</script>

<template>
    <DataTablesVue
        v-if="ready"
        :key="currentLocale"
        ref="dataTable"
        v-bind="$attrs"
        :ajax="ajax"
        :columns="columns"
        :data="data"
        :options="options"
        :class="classes"
        role="table"
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
    <div
        v-else
        class="h-40"
        aria-hidden="true"
    />
</template>
