<?php

namespace App\Services;

use App\Models\CustomField;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class AccountService
{
    public function __construct(private CustomFieldValueService $customFieldValueService) {}

    /**
     * @param  array<string, mixed>  $data
     * @param  Collection<int, CustomField>  $customFields
     */
    public function update(User $user, array $data, Collection $customFields): void
    {
        DB::transaction(function () use ($customFields, $data, $user): void {
            $user->update(Arr::except($data, 'custom_fields'));

            $this->customFieldValueService->sync(
                $user->customFieldValues(),
                $customFields,
                $data['custom_fields'] ?? [],
            );
        });
    }

    public function updatePassword(User $user, string $password): void
    {
        $user->update(['password' => $password]);
    }
}
