<?php

namespace Tests\Feature;

use App\Models\CustomField;
use App\Models\Event;
use App\Models\Person;
use App\Models\TeamEngagement;
use App\Models\TeamForm;
use App\Models\User;
use App\Support\OrganizationContext;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class TeamFormsTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_admin_form_surfaces_require_authentication_and_team_permission(): void
    {
        $this->get(route('team.forms'))->assertRedirect(route('login'));

        [$user] = $this->userWithCompletedSetup();
        Gate::define('view-team', fn (): bool => false);

        $this->actingAs($user)->get(route('team.forms'))->assertForbidden();

        Gate::define('view-team', fn (): bool => true);
        Gate::define('manage-team', fn (): bool => false);
        $this->actingAs($user)->get(route('team.forms.create'))->assertForbidden();
    }

    public function test_staff_can_create_multiple_team_forms_for_the_current_event(): void
    {
        [$user, $event] = $this->userWithCompletedSetup();

        foreach (['Volunteer application', 'Paid crew interest'] as $name) {
            $this->actingAs($user)
                ->post(route('team.forms.store', $event), $this->formPayload($name))
                ->assertSessionHasNoErrors();
        }

        $this->assertDatabaseCount('team_forms', 2);
        $this->assertDatabaseHas('team_forms', [
            'name' => 'Volunteer application',
            'slug' => 'volunteer-application',
        ]);
        $this->actingAs($user)
            ->get(route('team.forms'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Team/Forms')
                ->has('forms.data', 2)
                ->where('forms.data', fn ($forms): bool => collect($forms)
                    ->pluck('name')
                    ->sort()
                    ->values()
                    ->all() === ['Paid crew interest', 'Volunteer application']));
    }

    public function test_public_url_name_must_be_unique_and_url_safe(): void
    {
        [$user, $event] = $this->userWithCompletedSetup();
        $payload = $this->formPayload();
        $payload['slug'] = 'join our team';

        $this->actingAs($user)
            ->post(route('team.forms.store', $event), $payload)
            ->assertSessionHasErrors('slug');

        $payload['slug'] = 'join-our-team';
        $this->actingAs($user)
            ->post(route('team.forms.store', $event), $payload)
            ->assertSessionHasNoErrors();

        $payload['name'] = 'Another application';
        $this->actingAs($user)
            ->post(route('team.forms.store', $event), $payload)
            ->assertSessionHasErrors('slug');

        $this->get('/form/join-our-team')->assertOk();
        $this->assertDatabaseCount('team_forms', 1);
    }

    public function test_name_email_and_phone_must_remain_and_name_and_email_are_required(): void
    {
        [$user, $event] = $this->userWithCompletedSetup();
        $payload = $this->formPayload();
        $payload['fields'] = array_values(array_filter(
            $payload['fields'],
            fn (array $field): bool => $field['key'] !== 'phone',
        ));
        $this->actingAs($user)
            ->post(route('team.forms.store', $event), $payload)
            ->assertSessionHasErrors('fields');

        foreach (['name', 'email'] as $key) {
            $payload = $this->formPayload();
            $index = array_search($key, array_column($payload['fields'], 'key'), true);
            $payload['fields'][$index]['required'] = false;

            $this->actingAs($user)
                ->post(route('team.forms.store', $event), $payload)
                ->assertSessionHasErrors('fields');
        }

        $this->assertDatabaseCount('team_forms', 0);
    }

    public function test_staff_can_build_and_edit_a_team_custom_field_on_a_form(): void
    {
        [$user, $event] = $this->userWithCompletedSetup();
        $payload = $this->formPayload();
        $payload['fields'][] = [
            'id' => null,
            'key' => 'custom_preferred_role',
            'label' => 'Preferred role',
            'type' => 'select',
            'required' => false,
            'options' => ['Gate', 'Kitchen'],
        ];

        $this->actingAs($user)
            ->post(route('team.forms.store', $event), $payload)
            ->assertSessionHasNoErrors();

        $form = TeamForm::query()->sole();
        $customFormField = $form->fields()->whereNotNull('custom_field_id')->sole();
        $payload['name'] = 'Updated application';
        $payload['slug'] = 'updated-application';
        $payload['fields'][4] = [
            ...$payload['fields'][4],
            'id' => $customFormField->id,
            'label' => 'Best role',
            'required' => true,
        ];

        $this->actingAs($user)
            ->put(route('team.forms.update', $form), $payload)
            ->assertSessionHasNoErrors();

        $customField = CustomField::query()->where('target', CustomField::TARGET_TEAM_MEMBER)->sole();
        $this->assertSame('Best role', $customField->label);
        $this->assertSame(['Gate', 'Kitchen'], $customField->options);
        $this->assertTrue($customFormField->fresh()->required);
        $this->assertSame('Updated application', $form->fresh()->name);
        $this->assertSame('updated-application', $form->fresh()->slug);
        $this->get('/form/updated-application')->assertOk();
    }

    public function test_a_live_form_is_public_but_a_draft_form_is_not(): void
    {
        $event = $this->event();
        $live = $this->teamForm($event, 'live');
        $draft = $this->teamForm($event, 'draft');

        $this->get(route('team.forms.public.show', $live->slug))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Public/TeamForm')
                ->where('form.name', $live->name));
        $this->get(route('team.forms.public.show', $draft->slug))->assertNotFound();
    }

    public function test_public_submit_directly_creates_an_applied_team_member_with_custom_values(): void
    {
        $event = $this->event();
        $form = $this->teamForm($event);
        $customField = CustomField::query()->create([
            'target' => CustomField::TARGET_TEAM_MEMBER,
            'label' => 'Preferred role',
            'key' => 'preferred_role',
            'type' => 'text',
            'required' => false,
            'sort_order' => 1,
            'active' => true,
        ]);
        $form->fields()->create([
            'custom_field_id' => $customField->id,
            'key' => 'custom_'.$customField->id,
            'required' => false,
            'sort_order' => 4,
        ]);

        $response = $this->post(route('team.forms.public.store', $form->slug), [
            'name' => 'Sam Rivera',
            'email' => 'SAM@example.test',
            'phone' => '604-555-0100',
            'employment_type' => 'paid',
            'custom_fields' => [$customField->id => 'Gate'],
        ]);

        $engagement = TeamEngagement::query()->with('person')->sole();
        $response->assertRedirect(route('team.forms.public.confirmation', $form->slug));
        $this->assertSame('applied', $engagement->status);
        $this->assertSame('paid', $engagement->employment_type);
        $this->assertSame($form->id, $engagement->team_form_id);
        $this->assertNotNull($engagement->submitted_at);
        $this->assertSame('sam@example.test', $engagement->person->email);
        $this->assertDatabaseHas('custom_field_values', [
            'custom_field_id' => $customField->id,
            'custom_fieldable_type' => TeamEngagement::class,
            'custom_fieldable_id' => $engagement->id,
            'event_id' => $event->id,
            'value_text' => 'Gate',
        ]);
        $this->assertDatabaseCount('team_engagements', 1);
    }

    public function test_email_is_required_for_public_team_forms(): void
    {
        $event = $this->event();
        $form = $this->teamForm($event);

        $this->post(route('team.forms.public.store', $form->slug), [
            'name' => 'No Email Applicant',
            'employment_type' => 'volunteer',
        ])->assertSessionHasErrors('email');

        $this->assertDatabaseCount('people', 0);
        $this->assertDatabaseCount('team_engagements', 0);
    }

    public function test_public_submit_rejects_an_email_already_in_the_event_pipeline(): void
    {
        $event = $this->event();
        $form = $this->teamForm($event);
        $person = Person::query()->create(['name' => 'Existing', 'email' => 'existing@example.test']);
        TeamEngagement::query()->create([
            'event_id' => $event->id,
            'person_id' => $person->id,
            'status' => 'reviewing',
            'employment_type' => 'volunteer',
        ]);

        $this->post(route('team.forms.public.store', $form->slug), [
            'name' => 'Existing Again',
            'email' => 'EXISTING@example.test',
            'employment_type' => 'paid',
        ])->assertSessionHasErrors('email');

        $this->assertDatabaseCount('team_engagements', 1);
        $this->assertSame('reviewing', TeamEngagement::query()->sole()->status);
    }

    public function test_locked_events_reject_public_applications(): void
    {
        $event = $this->event();
        $form = $this->teamForm($event);
        $event->lock();

        $this->post(route('team.forms.public.store', $form->slug), [
            'name' => 'Locked Applicant',
            'email' => 'locked@example.test',
            'employment_type' => 'volunteer',
        ])->assertForbidden();

        $this->assertDatabaseCount('team_engagements', 0);
    }

    public function test_staff_cannot_edit_a_form_from_another_event(): void
    {
        [$user] = $this->userWithCompletedSetup();
        $foreignForm = $this->teamForm($this->event('Other Festival'));

        $this->actingAs($user)
            ->get(route('team.forms.edit', $foreignForm))
            ->assertNotFound();
    }

    /** @return array{User, Event} */
    private function userWithCompletedSetup(): array
    {
        $user = User::factory()->create();
        $event = $this->event();
        $organization = app(OrganizationContext::class);
        $organization->setDefaultEvent($event);
        $organization->markSetupComplete();
        $user->setCurrentEvent($event);

        return [$user, $event];
    }

    private function event(string $name = 'Festival'): Event
    {
        return Event::query()->create([
            'name' => $name,
            'starts_on' => '2027-06-01',
            'ends_on' => '2027-06-03',
            'timezone' => 'America/Vancouver',
        ]);
    }

    private function teamForm(Event $event, string $status = 'live'): TeamForm
    {
        $form = TeamForm::query()->create([
            'event_id' => $event->id,
            'name' => 'Team application',
            'slug' => 'team-application-'.str()->lower(str()->random(8)),
            'status' => $status,
        ]);

        foreach ($this->formPayload()['fields'] as $sortOrder => $field) {
            $form->fields()->create([
                'key' => $field['key'],
                'required' => $field['required'],
                'sort_order' => $sortOrder,
            ]);
        }

        return $form;
    }

    /** @return array<string, mixed> */
    private function formPayload(string $name = 'Team application'): array
    {
        return [
            'name' => $name,
            'slug' => str($name)->slug()->toString(),
            'status' => 'live',
            'fields' => [
                ['id' => null, 'key' => 'name', 'label' => 'Name', 'type' => 'text', 'required' => true, 'options' => []],
                ['id' => null, 'key' => 'email', 'label' => 'Email', 'type' => 'email', 'required' => true, 'options' => []],
                ['id' => null, 'key' => 'phone', 'label' => 'Phone', 'type' => 'phone', 'required' => false, 'options' => []],
                ['id' => null, 'key' => 'employment_type', 'label' => 'Volunteer or paid?', 'type' => 'select', 'required' => true, 'options' => ['volunteer', 'paid']],
            ],
        ];
    }
}
