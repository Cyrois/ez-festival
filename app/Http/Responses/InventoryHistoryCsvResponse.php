<?php

namespace App\Http\Responses;

use App\Http\Resources\StockMovementResource;
use App\Models\Event;
use Illuminate\Contracts\Support\Responsable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class InventoryHistoryCsvResponse implements Responsable
{
    public function __construct(private Event $event, private Builder $movements) {}

    public function toResponse($request): StreamedResponse
    {
        return response()->streamDownload(function () use ($request): void {
            $stream = fopen('php://output', 'w');
            fputcsv($stream, array_map(fn ($column) => __('inventory.columns.'.$column), [
                'when', 'movement', 'item', 'quantity', 'giver', 'receiver', 'recorded_by', 'note',
            ]), escape: '');
            foreach ($this->movements->lazy(500) as $movement) {
                $row = (new StockMovementResource($movement))->resolve($request);
                $receiver = $row['receiver'] ?? '—';
                if ($row['code'] !== null) {
                    $receiver .= ' · '.$row['code'];
                }
                fputcsv($stream, array_map($this->textCell(...), [
                    $movement->created_at->copy()->timezone($this->event->timezone)->format('Y-m-d H:i:s P'),
                    __('inventory.movements.'.$row['type']), $row['item'], (string) $row['quantity'],
                    $row['giver'] ?? '—', $receiver, $row['recorded_by'] ?? '—', $row['note'] ?? '—',
                ]), escape: '');
            }
            fclose($stream);
        }, 'inventory-history-'.(Str::slug($this->event->name) ?: 'event').'.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8', 'Cache-Control' => 'private, no-store',
        ]);
    }

    private function textCell(string $value): string
    {
        return preg_match('/^\s*[=+\-@]/u', $value) ? "'".$value : $value;
    }
}
