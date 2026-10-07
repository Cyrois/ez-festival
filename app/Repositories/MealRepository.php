<?php

namespace App\Repositories;

use App\Models\Event;

class MealRepository
{
    public function dataTable(Event $event, array $filters): array
    {
        $total = $event->meals()->count();

        return [
            'draw' => (int) $filters['draw'],
            'total' => $total,
            'meals' => $event->meals()->with('mealType')
                ->orderBy('date')->orderBy('starts_at')->orderBy('id')
                ->offset((int) $filters['start'])->limit((int) $filters['length'])->get(),
        ];
    }
}
