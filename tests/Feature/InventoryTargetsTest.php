<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Person;
use App\Models\Role;
use App\Models\TeamEngagement;
use App\Models\User;
use App\Queries\InventoryTargetsQuery;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class InventoryTargetsTest extends TestCase
{
    use LazilyRefreshDatabase;

    private Event $event;

    private InventoryTargetsQuery $targets;

    protected function setUp(): void
    {
        parent::setUp();
        $this->event = Event::create(['name' => 'Festival', 'starts_on' => '2027-06-01', 'ends_on' => '2027-06-03', 'timezone' => 'UTC']);
        $this->targets = app(InventoryTargetsQuery::class);
    }

    public function test_receiver_permissions_include_checkin_staff_managers_and_admins_without_team(): void
    {
        $checkin = $this->member(['checkin.edit']);
        $manager = $this->member(['inventory.manage']);
        $admin = $this->grantAdminAccess(User::factory()->create());
        foreach ([['checkin.view'], ['credentials.stock.adjust'], []] as $permissions) {
            $excluded = $this->member($permissions);
            $this->assertFalse($this->targets->canReceive($this->event, $excluded->person_id));
        }
        $this->assertEqualsCanonicalizing([$checkin->person_id, $manager->person_id, $admin->person_id],
            $this->targets->receivers($this->event)->pluck('p.id')->all());
        foreach ([$checkin, $manager, $admin] as $user) {
            $this->assertTrue($this->targets->canReceive($this->event, $user->person_id));
        }
        $this->assertTrue($this->targets->receivers($this->event)->where('p.id', $manager->person_id)->exists());
        $this->assertFalse($this->targets->receivers($this->event, $manager->person_id)->where('p.id', $manager->person_id)->exists());
        foreach (['inventory.pass_shift', 'inventory.pass_group'] as $key) {
            $this->assertTrue($this->targets->canReceive($this->event, $this->member([$key])->person_id));
        }
    }

    public function test_targets_require_hired_current_event_membership_active_roles_login_and_an_account(): void
    {
        foreach (['applied', 'reviewing', 'declined'] as $status) {
            $user = $this->member(['checkin.edit', 'inventory.manage'], $status);
            $this->assertFalse($this->targets->canReceive($this->event, $user->person_id));
        }
        $inactive = $this->member(['checkin.edit', 'inventory.manage']);
        $inactive->person->teamEngagements()->first()->role->update(['active' => false]);
        $disabled = $this->member(['checkin.edit', 'inventory.manage']);
        $disabled->person()->update(['can_log_in' => false]);
        $noRole = $this->member(['checkin.edit']);
        $noRole->person->teamEngagements()->update(['role_id' => null]);
        $foreign = $this->member(['checkin.edit', 'inventory.manage']);
        $other = Event::create(['name' => 'Other', 'starts_on' => '2027-07-01', 'ends_on' => '2027-07-03', 'timezone' => 'UTC']);
        $foreign->person->teamEngagements()->update(['event_id' => $other->id]);
        $noAccount = $this->member(['checkin.edit', 'inventory.manage']);
        $personId = $noAccount->person_id;
        $noAccount->delete();
        $this->assertFalse($this->targets->canReceive($this->event, $personId));
        $noPerson = $this->grantAdminAccess(User::factory()->create());
        $originalId = $noPerson->person_id;
        $noPerson->forceFill(['person_id' => null])->save();
        $this->assertFalse($this->targets->canReceive($this->event, $originalId));
        foreach ([$inactive, $disabled, $noRole, $foreign] as $user) {
            $this->assertFalse($this->targets->canReceive($this->event, $user->person_id));
            $this->assertFalse($this->targets->isManagerTarget($this->event, $user->person_id, $originalId));
        }
        $this->assertTrue($this->targets->canReceive($other, $foreign->person_id));
    }

    public function test_manager_targets_include_admins_once_exclude_self_and_do_not_require_checkin(): void
    {
        $actor = $this->member(['inventory.manage']);
        $manager = $this->member(['inventory.manage']);
        $checkin = $this->member(['checkin.edit']);
        $admin = $this->grantAdminAccess($this->member([]));
        $admin->person->teamEngagements()->first()->role->update(['active' => false]);
        $standaloneAdmin = $this->grantAdminAccess(User::factory()->create());
        $disabledAdmin = $this->grantAdminAccess(User::factory()->create());
        $disabledAdmin->person()->update(['can_log_in' => false]);
        $this->assertEqualsCanonicalizing([$manager->person_id, $admin->person_id, $standaloneAdmin->person_id],
            $this->targets->managers($this->event, $actor->person_id)->pluck('p.id')->all());
        $this->assertFalse($this->targets->isManagerTarget($this->event, $actor->person_id, $actor->person_id));
        $this->assertFalse($this->targets->isManagerTarget($this->event, $checkin->person_id, $actor->person_id));
        $this->assertTrue($this->targets->isManagerTarget($this->event, $manager->person_id, $actor->person_id));
        $this->assertFalse($this->targets->canReceive($this->event, $disabledAdmin->person_id));
    }

    public function test_target_revalidation_sees_permission_role_and_login_changes_without_a_cross_request_cache(): void
    {
        $user = $this->member(['inventory.manage', 'checkin.edit']);
        $role = $user->person->teamEngagements()->first()->role;
        $this->assertTrue($this->targets->canReceive($this->event, $user->person_id));
        $role->update(['permissions' => ['inventory.manage']]);
        $this->assertTrue($this->targets->canReceive($this->event, $user->person_id));
        $role->update(['permissions' => ['checkin.edit']]);
        $this->assertTrue($this->targets->canReceive($this->event, $user->person_id));
        $this->assertFalse($this->targets->isManagerTarget($this->event, $user->person_id, Person::create(['name' => 'Actor', 'email' => 'actor@example.test'])->id));
        $role->update(['permissions' => ['inventory.pass_group']]);
        $this->assertTrue($this->targets->canReceive($this->event, $user->person_id));
        $role->update(['permissions' => ['inventory.manage'], 'active' => false]);
        $this->assertFalse($this->targets->canReceive($this->event, $user->person_id));
        $role->update(['active' => true]);
        $user->person()->update(['can_log_in' => false]);
        $this->assertFalse($this->targets->canReceive($this->event, $user->person_id));
    }

    public function test_name_search_is_literal_and_pagination_is_batched_without_personal_info(): void
    {
        for ($index = 0; $index < 23; $index++) {
            $user = $this->member(['checkin.edit']);
            $user->person()->update(['name' => sprintf('Receiver %02d', $index), 'phone' => 'private-phone']);
        }
        $literal = $this->member(['inventory.manage']);
        $literal->person()->update(['name' => '100%_stock!']);
        $this->assertSame([$literal->person_id], $this->targets->receivers($this->event, search: '%_')->pluck('p.id')->all());
        DB::enableQueryLog();
        DB::flushQueryLog();
        $page = $this->targets->receivers($this->event, search: 'Receiver')->paginate(10, page: 2);
        $this->assertCount(2, DB::getQueryLog());
        DB::disableQueryLog();
        $this->assertSame(23, $page->total());
        $this->assertCount(10, $page->items());
        $this->assertSame('Receiver 10', $page->items()[0]->name);
        $this->assertSame(['id', 'name'], array_keys((array) $page->items()[0]));
    }

    private function member(array $permissions, string $status = 'hired'): User
    {
        $user = User::factory()->create();
        $role = Role::create(['name' => 'Role for '.$user->id, 'permissions' => $permissions]);
        TeamEngagement::create(['event_id' => $this->event->id, 'person_id' => $user->person_id,
            'role_id' => $role->id, 'status' => $status]);

        return $user;
    }
}
