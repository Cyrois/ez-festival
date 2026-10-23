<script setup>
import { Button } from '../../ui/button';
import { Card } from '../../ui/card';
import { Icon } from '../../ui/icon';

defineProps({
    loading: { type: Boolean, default: false },
    showingPeople: { type: Boolean, default: false },
    groupName: { type: String, default: '' },
});
const emit = defineEmits(['retry']);
</script>

<template>
    <Card v-if="loading">
        <div aria-hidden="true">
            <div
                v-for="row in 5"
                :key="row"
                class="grid grid-cols-3 gap-3 border-b border-line py-5 last:border-0 md:grid-cols-4 md:gap-6"
            >
                <span
                    v-for="column in 4"
                    :key="column"
                    class="h-3 animate-pulse rounded bg-line"
                    :class="column === 1 && 'max-md:col-span-3 max-md:w-1/2'"
                />
            </div>
        </div>
        <p
            role="status"
            class="mt-3 text-sm text-muted"
        >
            {{
                $t(
                    showingPeople
                        ? 'team.reports.people_loading'
                        : 'team.reports.loading',
                )
            }}
        </p>
    </Card>
    <Card v-else>
        <div
            role="alert"
            class="flex flex-wrap items-center gap-2 text-sm text-danger"
            :class="showingPeople && 'justify-center'"
        >
            <Icon
                v-if="showingPeople"
                :name="['fas', 'circle-exclamation']"
            />
            <span class="min-w-0 max-md:[overflow-wrap:anywhere]">{{
                $t(
                    showingPeople
                        ? 'team.reports.people_load_failed'
                        : 'team.reports.load_failed',
                    { name: groupName },
                )
            }}</span>
            <Button
                :variant="showingPeople ? 'link' : 'outline'"
                class="max-md:min-h-11 max-md:min-w-11"
                :class="showingPeople && 'h-auto px-0 underline'"
                :loading="loading"
                @click="emit('retry')"
                >{{ $t('team.reports.retry') }}</Button
            >
        </div>
    </Card>
</template>
