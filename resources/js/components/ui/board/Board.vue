<script setup>
import { computed, ref } from 'vue';
import { cn } from '../../../lib/utils';

const props = defineProps({
    columns: { type: Array, required: true },
    items: { type: Array, required: true },
    itemKey: { type: String, default: 'id' },
    columnKey: { type: String, default: 'status' },
    disabled: { type: Boolean, default: false },
    class: { type: [String, Object, Array], default: '' },
});

const emit = defineEmits(['move']);
const draggingKey = ref(null);
const overColumn = ref(null);

const groupedItems = computed(() =>
    Object.fromEntries(
        props.columns.map((column) => [
            column.value,
            props.items.filter(
                (item) => item[props.columnKey] === column.value,
            ),
        ]),
    ),
);

const startDrag = (event, item) => {
    if (props.disabled) {
        event.preventDefault();
        return;
    }

    draggingKey.value = item[props.itemKey];
    event.dataTransfer.effectAllowed = 'move';
    event.dataTransfer.setData('text/plain', String(item[props.itemKey]));
};

const finishDrag = () => {
    draggingKey.value = null;
    overColumn.value = null;
};

const dropItem = (column) => {
    const item = props.items.find(
        (candidate) => candidate[props.itemKey] === draggingKey.value,
    );

    if (item && item[props.columnKey] !== column.value) {
        emit('move', {
            item,
            from: item[props.columnKey],
            to: column.value,
        });
    }

    finishDrag();
};
</script>

<template>
    <div :class="cn('grid gap-3', props.class)">
        <section
            v-for="column in columns"
            :key="column.value"
            class="flex min-w-0 flex-col overflow-hidden rounded-lg border border-line bg-page"
            :aria-labelledby="`board-column-${column.value}`"
        >
            <header
                :id="`board-column-${column.value}`"
                :class="cn('px-3 py-2.5', column.headerClass)"
            >
                <slot
                    name="header"
                    :column="column"
                    :items="groupedItems[column.value]"
                />
            </header>
            <div
                :class="
                    cn(
                        'flex min-h-96 flex-1 flex-col gap-2 p-2 transition-colors',
                        overColumn === column.value &&
                            !disabled &&
                            'bg-primary/10 ring-2 ring-primary ring-inset',
                    )
                "
                @dragenter.prevent="overColumn = column.value"
                @dragover.prevent
                @drop.prevent="dropItem(column)"
            >
                <div
                    v-for="item in groupedItems[column.value]"
                    :key="item[itemKey]"
                    :draggable="!disabled"
                    :class="
                        cn(
                            !disabled && 'cursor-grab active:cursor-grabbing',
                            draggingKey === item[itemKey] && 'opacity-45',
                        )
                    "
                    @dragstart="startDrag($event, item)"
                    @dragend="finishDrag"
                >
                    <slot
                        name="item"
                        :item="item"
                        :column="column"
                    />
                </div>
                <slot
                    v-if="!groupedItems[column.value].length"
                    name="empty"
                    :column="column"
                />
            </div>
        </section>
    </div>
</template>
