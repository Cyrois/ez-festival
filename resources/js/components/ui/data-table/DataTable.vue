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
import {
    computed,
    nextTick,
    onBeforeUnmount,
    onMounted,
    ref,
    shallowRef,
    watch,
} from 'vue';
import { cn } from '../../../lib/utils';

defineOptions({ inheritAttrs: false });

DataTablesVue.use(DataTablesCore);

const props = defineProps({
    embedded: { type: Boolean, default: false },
    columnWidths: { type: Array, default: () => [] },
    expandedRowKey: { type: [String, Number], default: null },
    rowKey: { type: String, default: 'id' },
    mobileLayout: {
        type: String,
        default: 'stacked',
        validator: (value) => ['stacked', 'summary'].includes(value),
    },
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
const expansionTarget = shallowRef(null);
const expansionColumnWidths = shallowRef([]);
let expansionResizeObserver;
const measureExpansionColumns = (table) => {
    const widths = table
        .columns()
        .header()
        .toArray()
        .map((header) => header.getBoundingClientRect().width);
    if (!widths.some((width) => width > 0)) return;
    if (
        widths.some(
            (width, index) =>
                Math.abs(width - (expansionColumnWidths.value[index] ?? 0)) >
                0.1,
        )
    )
        expansionColumnWidths.value = widths;
};
const applyColumnWidths = (table) => {
    if (!table || !props.columnWidths.length) return;
    const columns = table
        .table()
        .node()
        .querySelector(':scope > colgroup')?.children;
    if (columns?.length !== props.columnWidths.length) return;
    const totalWidth = props.columnWidths.reduce(
        (total, width) => total + width,
        0,
    );
    if (totalWidth <= 0) return;
    [...columns].forEach((column, index) => {
        column.style.width = `${(props.columnWidths[index] / totalWidth) * 100}%`;
    });
};
const expandedRowData = computed(() =>
    props.expandedRowKey === null
        ? undefined
        : props.data?.find((row) => row[props.rowKey] === props.expandedRowKey),
);
let expandedRow;
const closeExpansion = () => {
    expansionResizeObserver?.disconnect();
    expansionResizeObserver = null;
    expansionColumnWidths.value = [];
    expandedRow?.node()?.classList.remove('dt-expanded');
    expandedRow?.child.remove();
    expandedRow = null;
    expansionTarget.value = null;
};
const syncExpansion = (table) => {
    if (!table) return;
    if (props.expandedRowKey === null) {
        if (expandedRow) closeExpansion();
        return;
    }
    const row = table.row(
        (_, data) =>
            props.expandedRowKey !== null &&
            data[props.rowKey] === props.expandedRowKey,
    );
    if (row.any() && expandedRow?.node() === row.node() && row.child.isShown())
        return;
    closeExpansion();
    if (!row.any()) return;
    const target = document.createElement('div');
    row.child(target, 'dt-expanded-child').show();
    row.node().classList.add('dt-expanded');
    expandedRow = row;
    expansionTarget.value = target;
    measureExpansionColumns(table);
    if (typeof ResizeObserver !== 'undefined') {
        expansionResizeObserver = new ResizeObserver(() =>
            measureExpansionColumns(table),
        );
        expansionResizeObserver.observe(table.table().node());
    }
};
const ready = ref(isLoaded(getActiveLanguage()));
const classes = computed(() =>
    cn(
        'dt-stacked w-full border-collapse text-left text-sm',
        props.mobileLayout === 'summary' && 'dt-summary',
        props.embedded && 'dt-embedded',
        props.columnWidths.length > 0 && 'table-fixed',
        props.class,
    ),
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
        const info = table.page.info();
        if (
            props.options.stateSave &&
            info.start > 0 &&
            info.start >= info.recordsDisplay
        ) {
            table.page(info.recordsDisplay ? 'last' : 'first').draw('page');
            return;
        }
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
        applyColumnWidths(table);
        syncExpansion(table);
    },
    language: {
        emptyTable: trans('data_table.empty'),
        info: trans('data_table.info'),
        infoEmpty: trans('data_table.info_empty'),
        infoFiltered: trans('data_table.info_filtered'),
        lengthMenu: trans('data_table.length_menu'),
        loadingRecords: `<span class="dt-loading">${trans('data_table.loading')}</span>`,
        processing: '',
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
watch([() => props.expandedRowKey, () => props.data, dataTable], async () => {
    await nextTick();
    syncExpansion(api());
});
watch(
    () => props.columnWidths,
    async () => {
        await nextTick();
        applyColumnWidths(api());
    },
);
onBeforeUnmount(closeExpansion);

const search = (value, resetPaging = true) => {
    api()?.search(value).draw(resetPaging);
};

// Named row filter on raw row data; pass null to clear it.
const filterRows = (name, predicate, resetPaging = true) => {
    api()
        ?.search.fixed(
            name,
            predicate ? (_, rowData) => predicate(rowData) : null,
        )
        .draw(resetPaging);
};

const reload = (resetPaging = true) => {
    api()?.ajax.reload(null, resetPaging);
};

// Set ordering before a reload without issuing a second server-side request.
const setOrder = (order) => {
    api()?.order(order);
};

const setColumnsVisible = (columns, visible) => {
    api()?.columns(columns).visible(visible);
};

const setPageLength = (length) => {
    api()?.page.len(length).page('first').draw();
};

defineExpose({
    dataTable,
    filterRows,
    reload,
    search,
    setOrder,
    setColumnsVisible,
    setPageLength,
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
        @column-sizing="applyColumnWidths(api())"
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
    <Teleport
        v-if="expansionTarget && expandedRowData"
        :to="expansionTarget"
    >
        <slot
            name="expandedRow"
            :row-data="expandedRowData"
            :column-widths="expansionColumnWidths"
        />
    </Teleport>
</template>
