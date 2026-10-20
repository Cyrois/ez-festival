<script setup>
import { computed } from 'vue';
import { Button } from '../../ui/button';
import { CustomDropdown } from '../../ui/custom-dropdown';
import { FormField } from '../../ui/form-field';
import { Icon } from '../../ui/icon';
import { Input } from '../../ui/input';

const groupId = defineModel('groupId', { type: [String, Number], default: '' });
const startDate = defineModel('startDate', { type: String, default: '' });
const endDate = defineModel('endDate', { type: String, default: '' });
const props = defineProps({
    groups: { type: Array, required: true },
    errors: { type: Object, default: () => ({}) },
});
const showingPeople = computed(() => groupId.value !== '');
const groupItems = computed(() =>
    props.groups.map((group) => ({ value: group.group_id, title: group.name })),
);
</script>

<template>
    <div class="space-y-6">
        <Button
            v-if="showingPeople"
            variant="ghost"
            class="px-0 text-muted"
            @click="groupId = ''"
        >
            <Icon :name="['fas', 'arrow-left']" />
            {{ $t('team.reports.back') }}
        </Button>
        <div>
            <div class="flex flex-wrap gap-3">
                <FormField
                    v-if="showingPeople"
                    v-slot="{ id }"
                    :label="$t('team.reports.group_picker')"
                    html-for="hours-group"
                    class="w-full sm:w-64"
                >
                    <CustomDropdown
                        :id="id"
                        v-model="groupId"
                        :items="groupItems"
                    />
                </FormField>
                <FormField
                    v-slot="{ id, invalid }"
                    :label="$t('team.reports.start_date')"
                    html-for="hours-start-date"
                    :error="errors.start_date"
                    class="w-full sm:w-44"
                >
                    <Input
                        :id="id"
                        v-model="startDate"
                        type="date"
                        :invalid="invalid"
                    />
                </FormField>
                <FormField
                    v-slot="{ id, invalid }"
                    :label="$t('team.reports.end_date')"
                    html-for="hours-end-date"
                    :error="errors.end_date"
                    class="w-full sm:w-44"
                >
                    <Input
                        :id="id"
                        v-model="endDate"
                        type="date"
                        :invalid="invalid"
                    />
                </FormField>
            </div>
            <p class="mt-3 text-xs text-muted">
                {{ $t('team.reports.range_hint') }}
            </p>
        </div>
    </div>
</template>
