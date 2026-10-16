<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorEngagement;
use App\Support\OrganizationContext;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AuditCustomFieldValidationTest extends TestCase
{
    use LazilyRefreshDatabase;

    public static function payloads(): array
    {
        $rows = [];
        foreach (['account', 'pass_create', 'pass_update', 'vendor_create', 'vendor_update'] as $form) {
            foreach (['absent' => [], 'null' => ['custom_fields' => null], 'empty' => ['custom_fields' => []], 'string' => ['custom_fields' => 'wrong'], 'number' => ['custom_fields' => 3], 'boolean' => ['custom_fields' => true], 'unknown' => ['custom_fields' => [999999 => 'wrong']]] as $name => $extra) {
                $rows[$form.' '.$name] = [$form, $extra, in_array($name, ['string', 'number', 'boolean', 'unknown'], true)];
            }
        }

        return $rows;
    }

    #[DataProvider('payloads')]
    public function test_optional_custom_fields_never_crash_validation(string $form, array $extra, bool $invalid): void
    {
        $this->withoutVite();
        $event = Event::create(['name' => 'Festival', 'starts_on' => '2027-06-01', 'ends_on' => '2027-06-03', 'timezone' => 'UTC']);
        app(OrganizationContext::class)->setDefaultEvent($event);
        app(OrganizationContext::class)->markSetupComplete();
        $user = User::factory()->create();
        $this->grantAdminAccess($user);
        $user->setCurrentEvent($event);
        $pass = $event->passTypes()->create(['name' => 'Original Pass']);
        $vendor = Vendor::create(['name' => 'Original Vendor']);
        $engagement = VendorEngagement::create(['vendor_id' => $vendor->id, 'event_id' => $event->id, 'status' => 'idea']);
        [$method,$url,$payload] = match ($form) {
            'account' => ['putJson', route('settings.account.update'), ['name' => 'Changed Account', 'email' => $user->email]],
            'pass_create' => ['postJson', route('credentials.passes.store', $event), ['name' => 'Changed Pass']],
            'pass_update' => ['putJson', route('credentials.passes.update', [$event, $pass]), ['name' => 'Changed Pass']],
            'vendor_create' => ['postJson', route('vendors.store', $event), ['name' => 'Changed Vendor', 'status' => 'idea']],
            'vendor_update' => ['putJson', route('vendors.update', $engagement), ['name' => 'Changed Vendor', 'status' => 'idea']],
        };
        $response = $this->actingAs($user)->$method($url, [...$payload, ...$extra]);
        if ($invalid) {
            $response->assertUnprocessable()->assertJsonValidationErrors('custom_fields');
            $this->assertSame('Original Pass', $pass->fresh()->name);
            $this->assertSame('Original Vendor', $vendor->fresh()->name);
            $this->assertSame($user->name, $user->fresh()->name);
            $this->assertDatabaseMissing('pass_types', ['name' => 'Changed Pass']);
            $this->assertDatabaseMissing('vendors', ['name' => 'Changed Vendor']);
        } else {
            $response->assertRedirect()->assertSessionHasNoErrors();
        }
    }
}
