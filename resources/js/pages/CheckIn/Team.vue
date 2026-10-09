<script setup>
import HiddenPersonalInfo from '../../components/people/HiddenPersonalInfo.vue';
import { computed, ref } from 'vue';
import AppLayout from '../../layouts/AppLayout.vue';
import { Avatar } from '../../components/ui/avatar';
import { Badge } from '../../components/ui/badge';
import { EmptyState } from '../../components/ui/empty-state';
import { Icon } from '../../components/ui/icon';
import { Button } from '../../components/ui/button';
import { Card } from '../../components/ui/card';
import TeamMemberShiftsCard from '../../components/team/TeamMemberShiftsCard.vue';
import EntitlementTable from '../../components/check-in/EntitlementTable.vue';
import ConsumeEntitlementDialog from '../../components/check-in/ConsumeEntitlementDialog.vue';
import { trans } from 'laravel-vue-i18n';

const props = defineProps({
    engagement: { type: Object, required: true },
    event: { type: Object, required: true },
    canWrite: { type: Boolean, required: true },
    memberUrl: { type: String, default: null },
    canViewShifts: { type: Boolean, required: true },
    checkInShifts: { type: Array, default: () => [] },
});

const consuming = ref(null);
const writeBlockedReason = computed(() =>
    props.event.locked
        ? trans('artists.check_in.locked')
        : trans('check_in.needs_edit_permission'),
);
const person = computed(() => props.engagement.people[0]);
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
            <header
                class="mb-5 flex flex-wrap items-center justify-between gap-3"
            >
                <div class="flex items-center gap-3">
                    <Avatar
                        :name="engagement.name"
                        size="lg"
                    />
                    <h1 class="m-0 text-2xl font-bold">
                        {{ engagement.name }}
                    </h1>
                </div>
                <Button
                    v-if="memberUrl"
                    :href="memberUrl"
                    variant="ghost"
                    >{{ $t('check_in.edit_member') }}</Button
                >
            </header>
            <p
                v-if="person && !person.has_pass"
                class="mb-4 flex items-center gap-2 rounded-lg border border-warning/20 bg-warning/10 p-3 text-sm text-charcoal"
                role="status"
            >
                <Icon :name="['fas', 'triangle-exclamation']" />
                {{ $t('check_in.no_pass_banner') }}
            </p>
            <p
                v-if="event.locked"
                class="mb-4 flex items-center gap-2 rounded-lg border border-warning/20 bg-warning/10 p-3 text-sm text-charcoal"
                role="status"
            >
                <Icon :name="['fas', 'lock']" />
                {{ $t('artists.check_in.locked') }}
            </p>
            <div
                class="grid items-stretch gap-5 lg:grid-cols-[18rem_minmax(0,1fr)]"
            >
                <aside v-if="person">
                    <Card class="h-full">
                        <div class="flex items-start gap-3">
                            <Avatar
                                :name="person.name"
                                size="sm"
                            />
                            <div class="min-w-0 flex-1">
                                <p class="m-0 text-sm font-bold">
                                    {{ person.name }}
                                </p>
                            </div>
                        </div>
                        <HiddenPersonalInfo
                            v-if="person.personal_info_hidden"
                            class="mt-4"
                        />
                        <dl class="mt-4 mb-0 space-y-3 text-sm">
                            <div>
                                <dt class="text-xs text-muted">
                                    {{ $t('team.member.fields.group') }}
                                </dt>
                                <dd class="m-0 break-words">
                                    {{
                                        engagement.group_name ??
                                        $t('team.member.fields.group_none')
                                    }}
                                </dd>
                            </div>
                            <div>
                                <dt class="text-xs text-muted">
                                    {{ $t('team.member.role.field') }}
                                </dt>
                                <dd class="m-0 break-words">
                                    {{
                                        engagement.role_name ??
                                        $t('team.member.role.no_role')
                                    }}
                                </dd>
                            </div>
                            <div
                                v-if="
                                    !person.personal_info_hidden && person.email
                                "
                            >
                                <dt class="text-xs text-muted">
                                    {{ $t('people.fields.email') }}
                                </dt>
                                <dd class="m-0 break-words">
                                    {{ person.email }}
                                </dd>
                            </div>
                            <div
                                v-if="
                                    !person.personal_info_hidden && person.phone
                                "
                            >
                                <dt class="text-xs text-muted">
                                    {{ $t('people.fields.phone') }}
                                </dt>
                                <dd class="m-0 break-words">
                                    {{ person.phone }}
                                </dd>
                            </div>
                        </dl>
                    </Card>
                </aside>
                <section
                    v-if="person"
                    class="h-full rounded-xl border border-line bg-ground p-5"
                >
                    <div class="mb-5 flex items-start justify-between gap-3">
                        <div>
                            <h2 class="m-0 text-xl font-bold">
                                {{ person.name }}
                            </h2>
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
                        v-if="person.passes.length"
                        class="mb-4 flex flex-wrap items-center gap-2 rounded-lg bg-primary-soft px-3 py-2"
                    >
                        <strong class="text-sm text-primary">
                            {{
                                $t('artists.check_in.pass_summary', {
                                    passes: person.passes.join(', '),
                                })
                            }}
                        </strong>
                    </div>
                    <EntitlementTable
                        v-if="person.expected > 0"
                        :key="person.id"
                        :type="engagement.type"
                        :engagement-id="engagement.id"
                        :person-id="person.id"
                        :refresh-key="person.issued"
                        :timezone="event.timezone"
                        :can-write="canWrite"
                        :write-blocked-reason="writeBlockedReason"
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
            <TeamMemberShiftsCard
                v-if="canViewShifts"
                :member-id="engagement.id"
                :check-in-shifts="checkInShifts"
            />
        </div>
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
