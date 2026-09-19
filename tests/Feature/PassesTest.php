<?php

namespace Tests\Feature;

use App\Models\CustomField;
use App\Models\Event;
use App\Models\Pass;
use App\Models\PassLabel;
use App\Models\User;
use App\Support\OrganizationContext;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PassesTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_guests_must_sign_in(): void
    {
        $this->get(route('credentials.passes'))->assertRedirect(route('login'));
        $this->get(route('credentials.passes.create'))->assertRedirect(route('login'));
    }

    public function test_passes_page_renders_for_a_user_with_completed_setup(): void
    {
        $user = User::factory()->create();
        $event = Event::query()->create([
            'name' => 'Sunrise Folk Fest 2026',
            'starts_on' => '2026-07-10',
            'ends_on' => '2026-07-12',
            'timezone' => 'America/Vancouver',
        ]);
        $organization = app(OrganizationContext::class);
        $organization->setDefaultEvent($event);
        $organization->markSetupComplete();
        $user->setCurrentEvent($event);

        $this->actingAs($user)->get(route('credentials.passes'))->assertInertia(
            fn (Assert $page) => $page
                ->component('Credentials/Passes')
                ->where('activeEvent.id', $event->id)
                ->where('activeEvent.name', 'Sunrise Folk Fest 2026'),
        );
    }

    public function test_passes_page_renders_pass_labels_for_filtering(): void
    {
        [$user, $event] = $this->createEventContext();
        $label = PassLabel::query()->create([
            'name' => 'Wristband',
            'color' => 'warning',
        ]);
        $pass = $event->passes()->create(['name' => 'Artist']);
        $pass->labels()->attach($label);

        $this->actingAs($user)->get(route('credentials.passes'))->assertInertia(
            fn (Assert $page) => $page
                ->component('Credentials/Passes')
                ->where('labels.0.id', $label->id)
                ->where('passes.0.labels.0.id', $label->id),
        );
    }

    public function test_create_page_renders_labels_and_pass_custom_fields(): void
    {
        [$user, $event] = $this->createEventContext();
        $label = PassLabel::query()->create([
            'name' => 'All-access',
            'color' => 'secondary',
        ]);
        $field = CustomField::query()->create([
            'target' => CustomField::TARGET_PASS,
            'label' => 'Print name',
            'key' => 'print_name',
            'type' => 'text',
            'required' => false,
            'sort_order' => 1,
            'active' => true,
        ]);

        $this->actingAs($user)->get(route('credentials.passes.create'))->assertInertia(
            fn (Assert $page) => $page
                ->component('Credentials/CreatePass')
                ->where('event.id', $event->id)
                ->where('labels.0.id', $label->id)
                ->where('customFields.0.id', $field->id),
        );
    }

    public function test_user_can_create_an_event_pass_with_labels_and_custom_fields(): void
    {
        [$user, $event] = $this->createEventContext();
        $existingLabel = PassLabel::query()->create([
            'name' => 'Wristband',
            'color' => 'warning',
        ]);
        $field = CustomField::query()->create([
            'target' => CustomField::TARGET_PASS,
            'label' => 'Access tier',
            'key' => 'access_tier',
            'type' => 'select',
            'required' => true,
            'options' => ['Standard', 'Backstage'],
            'sort_order' => 1,
            'active' => true,
        ]);

        $response = $this->actingAs($user)->post(route('credentials.passes.store', $event), [
            'name' => 'Artist',
            'max_assignments' => 50,
            'label_ids' => [$existingLabel->id],
            'new_labels' => [
                ['name' => 'Guest', 'color' => 'primary'],
            ],
            'custom_fields' => [
                $field->id => 'Backstage',
            ],
        ]);

        $response->assertRedirect(route('credentials.passes'));
        $response->assertSessionHas('success', 'Pass created.');

        $pass = Pass::query()->sole();
        $this->assertSame($event->id, $pass->event_id);
        $this->assertSame('Artist', $pass->name);
        $this->assertSame(50, $pass->max_assignments);
        $this->assertEqualsCanonicalizing(
            ['Guest', 'Wristband'],
            $pass->labels()->pluck('name')->all(),
        );
        $this->assertDatabaseHas('custom_field_values', [
            'custom_field_id' => $field->id,
            'event_id' => $event->id,
            'custom_fieldable_type' => Pass::class,
            'custom_fieldable_id' => $pass->id,
            'value_text' => 'Backstage',
        ]);
    }

    public function test_pass_name_is_unique_within_an_event_ignoring_case(): void
    {
        [$user, $event] = $this->createEventContext();
        $event->passes()->create(['name' => 'Artist']);

        $this->actingAs($user)->post(route('credentials.passes.store', $event), [
            'name' => ' artist ',
        ])->assertSessionHasErrors('name');

        $this->assertSame(1, Pass::query()->count());
    }

    public function test_locked_event_cannot_open_or_submit_the_create_pass_flow(): void
    {
        [$user, $event] = $this->createEventContext();
        $event->lock();

        $this->actingAs($user)
            ->get(route('credentials.passes.create'))
            ->assertForbidden();

        $this->actingAs($user)
            ->post(route('credentials.passes.store', $event), ['name' => 'Artist'])
            ->assertForbidden();
    }

    public function test_user_can_edit_a_pass_and_load_existing_custom_values(): void
    {
        [$user, $event] = $this->createEventContext();
        $label = PassLabel::query()->create(['name' => 'All-access', 'color' => 'primary']);
        $field = CustomField::query()->create([
            'target' => CustomField::TARGET_PASS,
            'label' => 'Print name',
            'key' => 'print_name',
            'type' => 'text',
            'required' => false,
            'sort_order' => 1,
            'active' => true,
        ]);
        $pass = $event->passes()->create(['name' => 'Artist', 'max_assignments' => 10]);
        $pass->labels()->attach($label);
        $pass->customFieldValues()->create([
            'custom_field_id' => $field->id,
            'event_id' => $event->id,
            'value_text' => 'Artist name',
            'value_search' => 'artist name',
        ]);

        $this->actingAs($user)->get(route('credentials.passes.edit', $pass))->assertInertia(
            fn (Assert $page) => $page
                ->component('Credentials/CreatePass')
                ->where('pass.id', $pass->id)
                ->where('pass.label_ids.0', $label->id)
                ->where("pass.custom_fields.{$field->id}", 'Artist name'),
        );

        $this->actingAs($user)->put(route('credentials.passes.update', [$event, $pass]), [
            'name' => 'Artist Plus',
            'max_assignments' => 12,
            'label_ids' => [],
            'custom_fields' => [$field->id => 'Headline artist'],
        ])->assertRedirect(route('credentials.passes'));

        $this->assertDatabaseHas('passes', ['id' => $pass->id, 'name' => 'Artist Plus', 'max_assignments' => 12]);
        $this->assertDatabaseHas('custom_field_values', ['custom_field_id' => $field->id, 'value_text' => 'Headline artist']);
    }

    public function test_entitlements_page_is_an_empty_stub(): void
    {
        [$user] = $this->createEventContext();

        $this->actingAs($user)->get(route('credentials.entitlements'))->assertInertia(
            fn (Assert $page) => $page->component('Credentials/Entitlements'),
        );
    }

    /**
     * @return array{User, Event}
     */
    private function createEventContext(): array
    {
        $user = User::factory()->create();
        $event = Event::query()->create([
            'name' => 'Sunrise Folk Fest 2026',
            'starts_on' => '2026-07-10',
            'ends_on' => '2026-07-12',
            'timezone' => 'America/Vancouver',
        ]);
        $organization = app(OrganizationContext::class);
        $organization->setDefaultEvent($event);
        $organization->markSetupComplete();
        $user->setCurrentEvent($event);

        return [$user, $event];
    }
}
