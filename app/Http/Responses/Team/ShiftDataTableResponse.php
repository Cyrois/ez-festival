<?php

namespace App\Http\Responses\Team;

use Illuminate\Contracts\Support\Responsable;
use Illuminate\Http\JsonResponse;

class ShiftDataTableResponse implements Responsable
{
    /**
     * @param  array<int, array<string, mixed>>  $data
     */
    public function __construct(
        private readonly int $draw,
        private readonly int $recordsTotal,
        private readonly int $recordsFiltered,
        private readonly array $data,
    ) {}

    public function toResponse($request): JsonResponse
    {
        return response()->json([
            'draw' => $this->draw,
            'recordsTotal' => $this->recordsTotal,
            'recordsFiltered' => $this->recordsFiltered,
            'data' => $this->data,
        ]);
    }
}
