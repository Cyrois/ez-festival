<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Repositories\PassTypeRepository;
use App\Services\EntitlementItemService;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AuditCacheCommitTest extends TestCase
{
    use DatabaseMigrations;

    public static function caches(): array
    {
        return [['pass'], ['stock']];
    }

    #[DataProvider('caches')]
    public function test_nested_invalidation_waits_for_outer_commit(string $kind): void
    {
        [$service,$event,$key] = $this->cache($kind);
        DB::beginTransaction();
        DB::transaction(fn () => $service->forgetList($event->id));
        $this->assertTrue(Cache::has($key));
        DB::commit();
        $this->assertFalse(Cache::has($key));
    }

    #[DataProvider('caches')]
    public function test_rollback_preserves_cached_committed_data(string $kind): void
    {
        [$service,$event,$key] = $this->cache($kind);
        DB::beginTransaction();
        DB::transaction(fn () => $service->forgetList($event->id));
        DB::rollBack();
        $this->assertTrue(Cache::has($key));
        $service->forgetList($event->id);
        $this->assertFalse(Cache::has($key));
    }

    private function cache(string $kind): array
    {
        $event = Event::create(['name' => 'Festival', 'starts_on' => '2027-06-01', 'ends_on' => '2027-06-03', 'timezone' => 'UTC']);
        $service = app($kind === 'pass' ? PassTypeRepository::class : EntitlementItemService::class);
        $service->list($event);
        $suffix = $kind === 'pass' ? 'pass-types.v2' : 'entitlement-items.v2';
        $key = "lists.events.{$event->id}.{$suffix}";
        $this->assertTrue(Cache::has($key));

        return [$service, $event, $key];
    }
}
