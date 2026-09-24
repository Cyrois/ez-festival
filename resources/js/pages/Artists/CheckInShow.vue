<script setup>
import { computed, ref } from 'vue';
import AppLayout from '../../layouts/AppLayout.vue';
import { Avatar } from '../../components/ui/avatar';
import { Badge } from '../../components/ui/badge';
import { EmptyState } from '../../components/ui/empty-state';
import { Icon } from '../../components/ui/icon';
import { Tag } from '../../components/ui/tag';
import PeopleRail from '../../components/check-in/PeopleRail.vue';
import EntitlementRows from '../../components/check-in/EntitlementRows.vue';
import EntitlementDetailsDialog from '../../components/check-in/EntitlementDetailsDialog.vue';
import ConsumeEntitlementDialog from '../../components/check-in/ConsumeEntitlementDialog.vue';
import { trans } from 'laravel-vue-i18n';

const props = defineProps({
    engagement: { type: Object, required: true },
    event: { type: Object, required: true },
    canWrite: { type: Boolean, required: true },
    selectedPersonId: { type: Number, default: null },
});

const selectedId = ref(
    props.engagement.people.some(({ id }) => id === props.selectedPersonId)
        ? props.selectedPersonId
        : (props.engagement.people[0]?.id ?? null),
);
const details = ref(null);
const consuming = ref(null);
const readOnly = computed(() => !props.canWrite);
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
    { label: trans('app.name'), href: '/dashboard' },
    { label: trans('check_in.title'), href: '/check-in' },
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
            <p
                v-if="readOnly"
                class="mb-4 flex items-center gap-2 rounded-lg border border-warning/20 bg-warning/10 p-3 text-sm text-charcoal"
                role="status"
            >
                <Icon :name="['fas', 'lock']" />
                {{ $t('artists.check_in.locked') }}
            </p>
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
                            <p class="mt-1 mb-0 text-xs text-muted">
                                {{ $t('artists.check_in.consume_hint') }}
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
                    <div
                        v-if="person.passes.length || person.pass_labels.length"
                        class="mb-4 flex flex-wrap items-center gap-2 rounded-lg bg-primary-soft px-3 py-2"
                    >
                        <strong class="text-sm text-primary">
                            {{
                                $t('artists.check_in.pass_summary', {
                                    passes: person.passes.join(', '),
                                })
                            }}
                        </strong>
                        <Tag
                            v-for="label in person.pass_labels"
                            :key="label.name"
                            :name="label.name"
                            :color="label.color"
                        />
                    </div>
                    <EntitlementRows
                        v-if="person.entitlements.length"
                        :entitlements="person.entitlements"
                        :can-write="canWrite"
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
            :timezone="event.timezone"
            @update:open="details = $event ? details : null"
        />
        <ConsumeEntitlementDialog
            :open="Boolean(consuming)"
            :entitlement="consuming"
            :endpoint="
                consuming
                    ? `/check-in/expected-entitlements/${consuming.id}/issues`
                    : ''
            "
            @update:open="consuming = $event ? consuming : null"
        />
    </AppLayout>
</template>
