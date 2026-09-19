<?php

namespace Tests\Feature\Settings;

use App\Models\ArtistType;
use App\Models\Event;
use App\Models\User;
use App\Support\OrganizationContext;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class TypeReorderTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_artist_type_reorder_validates_ids_and_normalizes_positions(): void
    {
        $user = $this->userWithCompletedSetup();
        $a = ArtistType::query()->create(['name' => 'Headliner', 'sort_order' => 0]);
        $b = ArtistType::query()->create(['name' => 'Support', 'sort_order' => 1]);
        $c = ArtistType::query()->create(['name' => 'Opener', 'sort_order' => 2]);

        $this->actingAs($user)->post(route('settings.artist-types.reorder'), [
            'types' => [
                ['id' => $c->id, 'position' => 10],
                ['id' => $a->id, 'position' => 5],
                ['id' => $b->id, 'position' => 7],
            ],
        ])->assertRedirect();

        // Sorted by submitted position then reindexed 0..n-1: a(5), b(7), c(10).
        $this->assertSame(0, $a->fresh()->sort_order);
        $this->assertSame(1, $b->fresh()->sort_order);
        $this->assertSame(2, $c->fresh()->sort_order);
    }

    public function test_artist_type_reorder_rejects_unknown_ids_and_duplicate_positions(): void
    {
        $user = $this->userWithCompletedSetup();
        $a = ArtistType::query()->create(['name' => 'Headliner', 'sort_order' => 0]);

        $this->actingAs($user)->post(route('settings.artist-types.reorder'), [
            'types' => [
                ['id' => $a->id, 'position' => 0],
                ['id' => 999999, 'position' => 1],
            ],
        ])->assertSessionHasErrors('types.1.id');

        $this->actingAs($user)->post(route('settings.artist-types.reorder'), [
            'types' => [
                ['id' => $a->id, 'position' => 0],
                ['id' => $a->id, 'position' => 1],
            ],
        ])->assertSessionHasErrors('types.1.id');
    }

    private function userWithCompletedSetup(): User
    {
        $user = User::factory()->create();
        $event = Event::query()->create([
            'name' => 'Festival',
            'starts_on' => '2027-06-01',
            'ends_on' => '2027-06-03',
            'timezone' => 'America/Vancouver',
        ]);
        $organization = app(OrganizationContext::class);
        $organization->setDefaultEvent($event);
        $organization->markSetupComplete();
        $user->setCurrentEvent($event);

        return $user;
    }
}
