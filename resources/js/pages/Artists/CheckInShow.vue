<script setup>
import { computed, ref } from 'vue';
import AppLayout from '../../layouts/AppLayout.vue';
import { Avatar } from '../../components/ui/avatar';
import { Badge } from '../../components/ui/badge';
import { EmptyState } from '../../components/ui/empty-state';
import PeopleRail from '../../components/check-in/PeopleRail.vue';
import EntitlementRows from '../../components/check-in/EntitlementRows.vue';
import EntitlementDetailsDialog from '../../components/check-in/EntitlementDetailsDialog.vue';
import ConsumeEntitlementDialog from '../../components/check-in/ConsumeEntitlementDialog.vue';
import { trans } from 'laravel-vue-i18n';

const props = defineProps({
    engagement: { type: Object, required: true },
    event: { type: Object, required: true },
});

const selectedId = ref(props.engagement.people[0]?.id ?? null);
const details = ref(null);
const consuming = ref(null);
const person = computed(() =>
    props.engagement.people.find(({ id }) => id === selectedId.value),
);
const status = computed(() => {
    if (
        !person.value ||
        person.value.expected === 0 ||
        person.value.issued >= person.value.expected
    )
        return 'complete';
    if (person.value.issued === 0) return 'not_started';
    return 'partial';
});
const breadcrumbs = computed(() => [
    { label: trans('nav.artists'), href: '/artists/advancing' },
    { label: trans('artists.check_in.title'), href: '/artists/check-in' },
    { label: props.engagement.name },
]);
</script>

<template>
    <AppLayout
        :title="engagement.name"
        :breadcrumbs="breadcrumbs"
    >
        <div class="container mx-auto">
            <header class="mb-5 flex items-center gap-3">
                <Avatar
                    :name="engagement.name"
                    size="lg"
                />
                <div>
                    <h1 class="m-0 text-2xl font-bold">
                        {{ engagement.name }}
                    </h1>
                    <p class="m-0 text-sm text-muted">
                        {{ $t('artists.check_in.engagement_confirmed') }}
                    </p>
                </div>
            </header>
            <p class="mb-5 text-sm text-muted">
                {{ $t('artists.check_in.select_person') }}
            </p>
            <div class="grid gap-5 lg:grid-cols-[18rem_minmax(0,1fr)]">
                <PeopleRail
                    :people="engagement.people"
                    :selected-id="selectedId"
                    @select="selectedId = $event"
                />
                <section
                    v-if="person"
                    class="rounded-xl border border-line bg-ground p-5"
                >
                    <div class="mb-5 flex items-start justify-between gap-3">
                        <div>
                            <h2 class="m-0 text-xl font-bold">
                                {{ person.name }}
                            </h2>
                            <p class="m-0 text-sm font-semibold">
                                {{
                                    person.is_primary
                                        ? $t('artists.check_in.primary_contact')
                                        : $t('artists.check_in.contact')
                                }}
                            </p>
                        </div>
                        <Badge
                            :variant="
                                status === 'partial' ? 'warning' : 'neutral'
                            "
                            pill
                            >{{
                                $t(`artists.check_in.status.${status}`)
                            }}</Badge
                        >
                    </div>
                    <EntitlementRows
                        v-if="person.entitlements.length"
                        :entitlements="person.entitlements"
                        :can-write="!event.locked"
                        @details="details = $event"
                        @consume="consuming = $event"
                    />
                    <EmptyState
                        v-else
                        :title="$t('artists.check_in.no_entitlements')"
                        :description="
                            $t('artists.check_in.no_entitlements_description')
                        "
                    />
                </section>
            </div>
        </div>
        <EntitlementDetailsDialog
            :open="Boolean(details)"
            :entitlement="details"
            @update:open="details = $event ? details : null"
        />
        <ConsumeEntitlementDialog
            :open="Boolean(consuming)"
            :entitlement="consuming"
            :endpoint="
                consuming
                    ? `/artists/check-in/expected-entitlements/${consuming.id}/issues`
                    : ''
            "
            @update:open="consuming = $event ? consuming : null"
        />
    </AppLayout>
</template>
