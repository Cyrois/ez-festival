<?php

namespace App\Repositories;

use App\Models\Shift;
use App\Models\ShiftAssignment;
use Illuminate\Support\Collection;

class ShiftBreakRepository
{
    public function defaults(Shift $shift): Collection
    {
        return $shift->breaks()->get()->keyBy('id');
    }

    public function personal(ShiftAssignment $assignment): Collection
    {
        return $assignment->breaks()->lockForUpdate()->get()->keyBy('id');
    }

    public function syncPersonal(ShiftAssignment $assignment, array $rows, Collection $existing): void
    {
        $kept = [];
        foreach ($rows as $index => $row) {
            $break = isset($row['id']) ? $existing->get($row['id']) : null;
            unset($row['id']);
            $row['sort_order'] = $index;
            if ($break) {
                $break->update($row);
            } else {
                $break = $assignment->breaks()->create($row);
            }
            $kept[] = $break->id;
        }
        $assignment->breaks()->whereNotIn('id', $kept)->delete();
    }
}
