<?php

namespace App\Services;

use App\Models\AppConfig;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Throwable;

class FeatureFlagService
{
    private const CACHE_KEY = 'feature_flags:v1';

    /**
     * @return array<string, bool>
     */
    public function all(): array
    {
        $overrides = Cache::remember(self::CACHE_KEY, now()->addMinute(), function (): array {
            try {
                return AppConfig::query()
                    ->pluck('enabled', 'key')
                    ->map(fn (mixed $enabled): bool => (bool) $enabled)
                    ->all();
            } catch (Throwable $exception) {
                report($exception);

                return [];
            }
        });

        return collect($this->definitions())
            ->mapWithKeys(fn (array $definition, string $key): array => [
                $key => (bool) ($overrides[$key] ?? $definition['default']),
            ])
            ->all();
    }

    public function enabled(string $key): bool
    {
        $this->ensureExists($key);

        return $this->all()[$key];
    }

    public function exists(string $key): bool
    {
        return array_key_exists($key, $this->definitions());
    }

    public function set(string $key, bool $enabled, ?User $actor): void
    {
        $this->ensureExists($key);

        $previous = $this->enabled($key);

        AppConfig::query()->updateOrCreate(
            ['key' => $key],
            [
                'enabled' => $enabled,
                'updated_by_user_id' => $actor?->id,
            ],
        );

        Cache::forget(self::CACHE_KEY);

        Log::info('Feature flag updated.', [
            'feature' => $key,
            'previous_enabled' => $previous,
            'enabled' => $enabled,
            'updated_by_user_id' => $actor?->id,
        ]);
    }

    /**
     * @return array<string, array{default: bool, label: string, description: string}>
     */
    public function definitions(): array
    {
        return config('features', []);
    }

    private function ensureExists(string $key): void
    {
        if (! $this->exists($key)) {
            throw new InvalidArgumentException("Unknown feature flag [{$key}].");
        }
    }
}
