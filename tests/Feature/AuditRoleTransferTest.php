<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Person;
use App\Models\Role;
use App\Models\TeamEngagement;
use App\Models\User;
use App\Support\OrganizationContext;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AuditRoleTransferTest extends TestCase
{
    use LazilyRefreshDatabase;

    public static function rolePayloads(): array
    {
        return [[false], [true]];
    }

    #[DataProvider('rolePayloads')]
    public function test_email_cannot_transfer_an_existing_role(bool $includeRole): void
    {
        $this->withoutVite();
        $event = Event::create(['name' => 'Festival', 'starts_on' => '2027-06-01', 'ends_on' => '2027-06-03', 'timezone' => 'UTC']);
        app(OrganizationContext::class)->setDefaultEvent($event);
        app(OrganizationContext::class)->markSetupComplete();
        $editor = User::factory()->create();
        $editor->setCurrentEvent($event);
        $this->grantRoleAccess($editor, ['team.edit']);
        $target = User::factory()->create();
        $target->person()->update(['can_log_in' => true]);
        $role = Role::create(['name' => 'Powerful role', 'permissions' => ['team.change_role']]);
        $original = Person::create(['name' => 'Original', 'email' => 'original@example.test']);
        $member = TeamEngagement::create(['event_id' => $event->id, 'person_id' => $original->id, 'role_id' => $role->id, 'status' => 'hired', 'employment_type' => 'volunteer']);
        $payload = ['name' => $target->name, 'email' => $target->email, 'status' => 'hired', 'employment_type' => 'volunteer'];
        if ($includeRole) {
            $payload['role_id'] = $role->id;
        }
        $this->actingAs($editor)->put(route('team.members.update', $member), $payload)->assertSessionHasErrors('email');
        $this->assertSame($original->id, $member->fresh()->person_id);
        $this->assertSame($role->id, $member->fresh()->role_id);
        $this->assertFalse($target->person->teamEngagements()->whereBelongsTo($event)->exists());
    }
}
