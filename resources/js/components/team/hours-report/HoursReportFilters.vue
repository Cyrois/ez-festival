<script setup>
import HoursReportEmploymentFilter from './HoursReportEmploymentFilter.vue';
import { computed } from 'vue';
import { trans } from 'laravel-vue-i18n';
import { CustomDropdown } from '../../ui/custom-dropdown';
import { FormField } from '../../ui/form-field';
import { Input } from '../../ui/input';

const employmentType = defineModel('employmentType', {
    type: String,
    default: 'all',
});
const locationId = defineModel('locationId', {
    type: [String, Number],
    default: '',
});
const groupId = defineModel('groupId', { type: [String, Number], default: '' });
const startDate = defineModel('startDate', { type: String, default: '' });
const endDate = defineModel('endDate', { type: String, default: '' });
const props = defineProps({
    employmentOptions: { type: Array, default: () => [] },
    groups: { type: Array, default: () => [] },
    locations: { type: Array, default: () => [] },
    byLocation: { type: Boolean, default: false },
    showingPeople: { type: Boolean, default: false },
    errors: { type: Object, default: () => ({}) },
});
const groupItems = computed(() =>
    props.groups.map((group) => ({ value: group.group_id, title: group.name })),
);
const locationItems = computed(() => [
    { value: '', title: trans('team.reports.all_locations') },
    ...props.locations.map((location) => ({
        value: location.location_id,
        title: location.name,
    })),
]);
</script>

<template>
    <div class="space-y-6">
        <div>
            <div class="flex flex-wrap gap-3">
                <FormField
                    v-if="byLocation"
                    v-slot="{ id }"
                    :label="$t('team.reports.location')"
                    html-for="hours-location"
                    class="w-full sm:w-64"
                >
                    <CustomDropdown
                        :id="id"
                        v-model="locationId"
                        :items="locationItems"
                    />
                </FormField>
                <FormField
                    v-if="showingPeople && !byLocation"
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
                <HoursReportEmploymentFilter
                    v-model="employmentType"
                    :options="employmentOptions"
                />
            </div>
            <p class="mt-3 text-xs text-muted">
                {{ $t('team.reports.range_hint') }}
            </p>
        </div>
    </div>
</template>
