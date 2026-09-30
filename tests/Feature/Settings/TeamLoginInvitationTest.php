<?php

namespace Tests\Feature\Settings;

use App\Mail\TeamInvitationMail;
use App\Models\Event;
use App\Models\Person;
use App\Models\Role;
use App\Models\TeamEngagement;
use App\Models\User;
use App\Services\LoginInvitationService;
use App\Support\OrganizationContext;
use App\Support\TeamPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class TeamLoginInvitationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Mail::fake();
    }

    public function test_enabling_login_sends_an_invite_only_when_the_switch_is_newly_on(): void
    {
        [$actor, $person, $event] = $this->teamPerson();

        $this->actingAs($actor)->put(route('settings.team.update', $person), $this->updateData($person, $event, true))
            ->assertSessionHasNoErrors();

        $login = $person->user()->sole();
        $this->assertNull($login->password);
        $this->assertFalse($login->has_set_password);
        $this->assertDatabaseCount('login_invitations', 1);
        Mail::assertSent(TeamInvitationMail::class, function (TeamInvitationMail $mail) use ($actor, $person): bool {
            return $mail->hasTo($person->email)
                && $mail->inviterName === $actor->name
                && $mail->envelope()->subject === "You've been invited to Artist-Tree"
                && str_contains($mail->render(), 'Set your password')
                && str_contains($mail->render(), 'expires after 7 days');
        });

        Mail::fake();
        $this->actingAs($actor)->put(route('settings.team.update', $person), $this->updateData($person, $event, true));
        Mail::assertNothingSent();
    }

    public function test_invite_sets_password_once_and_expires_after_seven_days(): void
    {
        [$actor, $person, $event] = $this->teamPerson();
        $token = $this->enableAndToken($actor, $person, $event);
        $this->post(route('logout'));

        $this->get(route('team-invitations.show', $token))->assertInertia(
            fn (Assert $page) => $page->component('Auth/SetInvitedPassword')->where('valid', true),
        );
        $this->put(route('team-invitations.update', $token), [
            'password' => 'festival1',
            'password_confirmation' => 'festival1',
        ])->assertRedirect(route('login'));

        $login = $person->user->fresh();
        $this->assertTrue(Hash::check('festival1', $login->password));
        $this->assertTrue($login->has_set_password);
        $this->post('/login', [
            'email' => $person->email,
            'password' => 'festival1',
        ])->assertRedirect();
        $this->post(route('logout'));
        $this->get(route('team-invitations.show', $token))->assertInertia(
            fn (Assert $page) => $page->where('valid', false),
        );

        $person->user->update(['has_set_password' => false, 'password' => null]);
        $expiredToken = $this->sendInviteAndToken($actor, $person);
        $this->travel(8)->days();
        $this->actingAs($actor)->get(route('settings.team.show', $person))->assertInertia(
            fn (Assert $page) => $page->where('person.can_resend_invite', true),
        );
        $this->post(route('logout'));
        $this->get(route('team-invitations.show', $expiredToken))->assertInertia(
            fn (Assert $page) => $page->where('valid', false),
        );
    }

    public function test_password_rule_is_applied_to_invite_and_temporary_change_pages(): void
    {
        [$actor, $person, $event] = $this->teamPerson();
        $token = $this->enableAndToken($actor, $person, $event);
        $this->post(route('logout'));

        foreach (['short1', 'onlyletters', '12345678'] as $password) {
            $this->put(route('team-invitations.update', $token), [
                'password' => $password,
                'password_confirmation' => $password,
            ])->assertSessionHasErrors('password');
        }

        $login = $person->user;
        $login->forceFill(['password' => 'Temporary1!', 'must_change_password' => true])->save();
        $this->actingAs($login)->put(route('password.temporary.update'), [
            'password' => 'onlyletters',
            'password_confirmation' => 'onlyletters',
        ])->assertSessionHasErrors('password');
    }

    public function test_resending_replaces_the_old_link_and_disabling_login_cancels_it(): void
    {
        [$actor, $person, $event] = $this->teamPerson();
        $oldToken = $this->enableAndToken($actor, $person, $event);

        Mail::fake();
        $this->actingAs($actor)->post(route('settings.team.invite.store', $person))->assertSessionHasNoErrors();
        $newToken = $this->sentToken();
        $this->assertNotSame($oldToken, $newToken);
        $this->post(route('logout'));
        $this->get(route('team-invitations.show', $oldToken))->assertInertia(
            fn (Assert $page) => $page->where('valid', false),
        );
        $this->get(route('team-invitations.show', $newToken))->assertInertia(
            fn (Assert $page) => $page->where('valid', true),
        );

        $this->actingAs($actor)->put(route('settings.team.update', $person), $this->updateData($person, $event, false));
        $this->assertDatabaseCount('login_invitations', 0);
        $this->post(route('logout'));
        $this->get(route('team-invitations.show', $newToken))->assertInertia(
            fn (Assert $page) => $page->where('valid', false),
        );
    }

    public function test_reenabling_login_does_not_invite_someone_who_has_set_a_password(): void
    {
        [$actor, $person, $event] = $this->teamPerson();
        $person->update(['can_log_in' => true]);
        $login = User::query()->create([
            'person_id' => $person->id,
            'password' => 'festival1',
            'has_set_password' => true,
        ]);

        $person->update(['can_log_in' => false]);
        Mail::fake();
        $this->actingAs($actor)->put(route('settings.team.update', $person), $this->updateData($person, $event, true));

        Mail::assertNothingSent();
        $this->assertTrue(Hash::check('festival1', $login->fresh()->password));
    }

    public function test_generating_a_password_returns_it_once_hashes_it_and_cancels_the_invite(): void
    {
        [$actor, $person, $event] = $this->teamPerson();
        $inviteToken = $this->enableAndToken($actor, $person, $event);
        Mail::fake();

        $response = $this->actingAs($actor)
            ->postJson(route('settings.team.temporary-password.store', $person))
            ->assertOk();
        $password = $response->json('data.temporary_password');

        $this->assertIsString($password);
        $this->assertMatchesRegularExpression('/[A-Za-z]/', $password);
        $this->assertMatchesRegularExpression('/[0-9]/', $password);
        $this->assertTrue(Hash::check($password, $person->user->fresh()->password));
        $this->assertTrue($person->user->fresh()->must_change_password);
        $this->assertDatabaseCount('login_invitations', 0);
        Mail::assertNothingSent();
        $this->post(route('logout'));
        $this->get(route('team-invitations.show', $inviteToken))->assertInertia(
            fn (Assert $page) => $page->where('valid', false),
        );

        $this->actingAs($actor)->get(route('settings.team.show', $person))
            ->assertInertia(fn (Assert $page) => $page->missing('temporary_password'));
    }

    public function test_generating_again_replaces_the_previous_password(): void
    {
        [$actor, $person, $event] = $this->teamPerson();
        $this->enableAndToken($actor, $person, $event);

        $old = $this->actingAs($actor)->postJson(route('settings.team.temporary-password.store', $person))
            ->json('data.temporary_password');
        $new = $this->actingAs($actor)->postJson(route('settings.team.temporary-password.store', $person))
            ->json('data.temporary_password');

        $this->assertNotSame($old, $new);
        $this->assertFalse(Hash::check($old, $person->user->fresh()->password));
        $this->assertTrue(Hash::check($new, $person->user->fresh()->password));
    }

    public function test_temporary_password_forces_a_change_before_other_pages(): void
    {
        [$actor, $person, $event] = $this->teamPerson();
        $this->enableAndToken($actor, $person, $event);
        $password = $this->actingAs($actor)
            ->postJson(route('settings.team.temporary-password.store', $person))
            ->json('data.temporary_password');
        $this->post(route('logout'));

        $this->post('/login', ['email' => $person->email, 'password' => $password])
            ->assertRedirect(route('password.temporary.edit'));
        $this->get(route('dashboard'))->assertRedirect(route('password.temporary.edit'));
        $this->put(route('password.temporary.update'), [
            'password' => 'festival2',
            'password_confirmation' => 'festival2',
        ])->assertRedirect();

        $login = $person->user->fresh();
        $this->assertFalse($login->must_change_password);
        $this->assertTrue($login->has_set_password);
        $this->assertFalse(Hash::check($password, $login->password));
        $this->assertTrue(Hash::check('festival2', $login->password));
    }

    public function test_generation_is_refused_when_login_is_off(): void
    {
        [$actor, $person] = $this->teamPerson();

        $this->actingAs($actor)
            ->postJson(route('settings.team.temporary-password.store', $person))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('can_log_in');
    }

    public function test_generate_and_resend_use_the_manage_team_permission(): void
    {
        [$actor, $person, $event] = $this->teamPerson();
        $this->enableAndToken($actor, $person, $event);
        Gate::define('manage-team', fn (): bool => false);

        $this->actingAs($actor)
            ->postJson(route('settings.team.temporary-password.store', $person))
            ->assertForbidden();
        $this->actingAs($actor)
            ->post(route('settings.team.invite.store', $person))
            ->assertForbidden();
    }

    public function test_generated_passwords_always_meet_the_shared_rule(): void
    {
        for ($i = 0; $i < 200; $i++) {
            $password = TeamPassword::temporary();
            $this->assertGreaterThanOrEqual(8, strlen($password));
            $this->assertMatchesRegularExpression('/[A-Za-z]/', $password);
            $this->assertMatchesRegularExpression('/[0-9]/', $password);
        }
    }

    /** @return array{User, Person, Event} */
    private function teamPerson(): array
    {
        $actor = User::factory()->create();
        $event = Event::query()->create([
            'name' => 'Festival',
            'starts_on' => '2027-06-01',
            'ends_on' => '2027-06-03',
            'timezone' => 'America/Vancouver',
        ]);
        app(OrganizationContext::class)->setDefaultEvent($event);
        app(OrganizationContext::class)->markSetupComplete();
        $actor->setCurrentEvent($event);
        $role = Role::query()->create(['name' => 'Staff']);
        $person = Person::query()->create([
            'name' => 'Morgan West',
            'email' => 'morgan.west@example.test',
            'can_log_in' => false,
        ]);
        TeamEngagement::query()->create([
            'event_id' => $event->id,
            'person_id' => $person->id,
            'role_id' => $role->id,
            'status' => 'hired',
            'employment_type' => 'volunteer',
        ]);

        return [$actor, $person, $event];
    }

    private function updateData(Person $person, Event $event, bool $canLogIn): array
    {
        return [
            'name' => $person->name,
            'phone' => null,
            'can_log_in' => $canLogIn,
            'event_access' => [[
                'event_id' => $event->id,
                'role_id' => $person->teamEngagements()->where('event_id', $event->id)->value('role_id'),
                'status' => null,
            ]],
        ];
    }

    private function enableAndToken(User $actor, Person $person, Event $event): string
    {
        $this->actingAs($actor)->put(route('settings.team.update', $person), $this->updateData($person, $event, true));

        return $this->sentToken();
    }

    private function sendInviteAndToken(User $actor, Person $person): string
    {
        app(LoginInvitationService::class)->send($person, $actor);

        return $this->sentToken();
    }

    private function sentToken(): string
    {
        $url = null;
        Mail::assertSent(TeamInvitationMail::class, function (TeamInvitationMail $mail) use (&$url): bool {
            $url = $mail->url;

            return true;
        });

        return Str::afterLast($url, '/');
    }
}
