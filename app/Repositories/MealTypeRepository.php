<?php

namespace App\Repositories;

use App\Models\Event;
use App\Models\MealType;
use App\Support\SqlLike;

class MealTypeRepository
{
    public function dataTable(Event $event, array $filters): array
    {
        $query = $event->mealTypes();
        $total = (clone $query)->count();
        $search = trim($filters['search']['value'] ?? '');
        if ($search !== '') {
            $query->whereRaw("name_key like ? escape '!'", ['%'.SqlLike::escape(MealType::normalizeName($search)).'%']);
        }
        $filtered = (clone $query)->count();
        $column = (int) ($filters['order'][0]['column'] ?? -1);
        $sort = match ($column) {
            0 => 'name_key',
            1 => 'starts_at',
            default => 'sort_order',
        };

        return [
            'draw' => (int) $filters['draw'],
            'total' => $total,
            'filtered' => $filtered,
            'types' => $query->orderBy($sort, $filters['order'][0]['dir'] ?? 'asc')->orderBy('id')
                ->offset((int) $filters['start'])->limit((int) $filters['length'])->get(),
        ];
    }
}
