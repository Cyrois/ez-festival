<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TeamPassAssignmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $expectedCount = (int) ($this->expected_entitlements_count ?? 0);
        $issuedCount = (int) ($this->issued_count ?? 0);

        return [
            'id' => $this->id,
            'pass_type_id' => $this->pass_type_id,
            'pass_name' => $this->passType->name,
            'labels' => $this->passType->labels->map->only(['id', 'name', 'color'])->values(),
            'expected_count' => $expectedCount,
            'issued_count' => $issuedCount,
            'issue_state' => $this->issueState($expectedCount, $issuedCount),
            'can_remove' => $issuedCount === 0,
        ];
    }

    private function issueState(int $expectedCount, int $issuedCount): string
    {
        if ($issuedCount === 0) {
            return 'unissued';
        }

        return $issuedCount < $expectedCount ? 'partially_issued' : 'issued';
    }
}
