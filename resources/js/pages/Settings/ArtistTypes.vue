<script setup>
import SettingsLayout from '../../layouts/SettingsLayout.vue';
import { Button } from '../../components/ui/button';
import { Card } from '../../components/ui/card';
import { EmptyState } from '../../components/ui/empty-state';
import { Icon } from '../../components/ui/icon';
import { router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { trans } from 'laravel-vue-i18n';

const props = defineProps({
    types: {
        type: Array,
        default: () => [],
    },
});

const orderedTypes = ref([...props.types]);
const draggedId = ref(null);
const activeDropIndex = ref(null);

const breadcrumbs = computed(() => [
    { label: trans('app.name'), href: '/dashboard' },
    { label: trans('nav.settings'), href: '/settings/events' },
    { label: trans('settings.artist_types.title') },
]);

const persistOrder = () => {
    router.post(
        '/settings/artist-types/reorder',
        {
            types: orderedTypes.value.map((type, position) => ({
                id: type.id,
                position,
            })),
        },
        {
            preserveScroll: true,
            onError: () => {
                orderedTypes.value = [...props.types];
            },
        },
    );
};

const startDrag = (event, id) => {
    draggedId.value = id;
    activeDropIndex.value = null;
    event.dataTransfer.effectAllowed = 'move';
};

const endDrag = () => {
    draggedId.value = null;
    activeDropIndex.value = null;
};

const updateDropIndex = (event, index) => {
    if (draggedId.value === null) {
        return;
    }

    const bounds = event.currentTarget.getBoundingClientRect();
    activeDropIndex.value =
        event.clientY < bounds.top + bounds.height / 2 ? index : index + 1;
};

const dropType = () => {
    const targetIndex = activeDropIndex.value;
    const from = orderedTypes.value.findIndex(
        (type) => type.id === draggedId.value,
    );

    if (targetIndex === null || from === -1) {
        endDrag();
        return;
    }

    let to = targetIndex;
    if (from < to) {
        to -= 1;
    }

    if (from === to) {
        endDrag();
        return;
    }

    const [moved] = orderedTypes.value.splice(from, 1);
    orderedTypes.value.splice(to, 0, moved);
    endDrag();
    persistOrder();
};
</script>

<template>
    <SettingsLayout
        :title="$t('settings.artist_types.title')"
        :breadcrumbs="breadcrumbs"
    >
        <div class="mb-6 flex items-start justify-between gap-3">
            <div>
                <h1 class="m-0 text-2xl font-bold tracking-tight">
                    {{ $t('settings.artist_types.title') }}
                </h1>
                <p class="mt-1 mb-0 text-sm text-muted">
                    {{ $t('settings.artist_types.lead') }}
                </p>
            </div>
            <Button
                variant="primary"
                size="sm"
                disabled
            >
                <Icon
                    :name="['fas', 'plus']"
                    size="sm"
                    class="mr-1.5"
                />
                {{ $t('settings.type_actions.create') }}
            </Button>
        </div>

        <EmptyState
            v-if="orderedTypes.length === 0"
            :title="$t('settings.artist_types.empty')"
        >
            <template #icon>
                <Icon
                    :name="['fas', 'music']"
                    size="lg"
                />
            </template>
        </EmptyState>

        <div
            v-else
            class="flex flex-col gap-3"
        >
            <div
                v-if="draggedId !== null && activeDropIndex === 0"
                class="min-h-[72px] rounded-lg border-2 border-dashed border-primary bg-primary/10"
                @dragover.prevent
                @drop.prevent="dropType"
            />
            <Card
                v-for="(type, index) in orderedTypes"
                :key="type.id"
                class="p-4"
                :class="
                    draggedId === type.id
                        ? 'ring-2 ring-primary ring-offset-2'
                        : ''
                "
                @dragover.prevent="updateDropIndex($event, index)"
                @drop.prevent="dropType"
            >
                <div class="flex items-center gap-3">
                    <Button
                        type="button"
                        variant="ghost"
                        size="sm"
                        class="cursor-grab touch-none px-2 text-muted active:cursor-grabbing"
                        :aria-label="$t('settings.type_actions.drag_handle')"
                        draggable="true"
                        @dragstart="startDrag($event, type.id)"
                        @dragend="endDrag"
                    >
                        <Icon
                            :name="['fas', 'grip-lines']"
                            fixed-width
                        />
                    </Button>
                    <strong class="min-w-0 flex-1 font-bold">{{
                        type.name
                    }}</strong>
                    <div class="flex shrink-0 gap-2">
                        <Button
                            variant="outline"
                            size="sm"
                            disabled
                        >
                            <Icon
                                :name="['fas', 'pencil']"
                                size="sm"
                                class="mr-1.5"
                            />
                            {{ $t('settings.type_actions.edit') }}
                        </Button>
                        <Button
                            variant="outline"
                            size="sm"
                            disabled
                        >
                            <Icon
                                :name="['fas', 'trash-can']"
                                size="sm"
                                class="mr-1.5"
                            />
                            {{ $t('settings.type_actions.delete') }}
                        </Button>
                    </div>
                </div>
            </Card>
            <div
                v-if="
                    draggedId !== null &&
                    activeDropIndex === orderedTypes.length
                "
                class="min-h-[72px] rounded-lg border-2 border-dashed border-primary bg-primary/10"
                @dragover.prevent
                @drop.prevent="dropType"
            />
        </div>
    </SettingsLayout>
</template>
