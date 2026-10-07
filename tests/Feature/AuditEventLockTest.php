<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\TeamForm;
use App\Models\User;
use App\Services\TeamFormService;
use App\Support\OrganizationContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class AuditEventLockTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_team_form_settings_recheck_a_stale_event_lock(): void
    {
        [, $event] = $this->context();
        $form = TeamForm::create(['event_id' => $event->id, 'name' => 'Original', 'slug' => 'original', 'status' => 'draft']);
        $form->load('event');
        Event::findOrFail($event->id)->lock();
        $service = app(TeamFormService::class);
        $data = ['name' => 'Changed', 'slug' => 'changed', 'status' => 'draft', 'fields' => []];
        foreach (['create', 'update'] as $operation) {
            try {
                $operation === 'create' ? $service->create($event, $data) : $service->update($form, $data);
                $this->fail('A stale event must not authorize Team form writes.');
            } catch (HttpException $exception) {
                $this->assertSame(403, $exception->getStatusCode());
            }
        }
        $this->assertSame(['Original'], $event->teamForms()->pluck('name')->all());
        $this->assertSame('original', $form->fresh()->slug);
    }

    private function context(): array
    {
        $event = Event::create(['name' => 'Database Regression', 'starts_on' => '2027-06-01', 'ends_on' => '2027-06-03', 'timezone' => 'America/Vancouver']);
        $organization = app(OrganizationContext::class);
        $organization->setDefaultEvent($event);
        $organization->markSetupComplete();
        $user = User::factory()->create();
        $this->grantAdminAccess($user);
        $user->setCurrentEvent($event);

        return [$user, $event];
    }
}
