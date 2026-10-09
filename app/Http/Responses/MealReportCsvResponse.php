<?php

namespace App\Http\Responses;

use Illuminate\Contracts\Support\Responsable;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MealReportCsvResponse implements Responsable
{
    public function __construct(private string $eventName, private array $rows) {}

    public function toResponse($request): StreamedResponse
    {
        return response()->streamDownload(function (): void {
            $stream = fopen('php://output', 'w');
            fputcsv($stream, array_map(fn ($column) => __('reports.meals.columns.'.$column), ['meal_name', 'date', 'meal_type', 'projected', 'used', 'remaining', 'extras', 'total']), escape: '');
            foreach ($this->rows as $row) {
                $name = $this->textCell($row['meal_name']);
                $type = $this->textCell($row['meal_type']);
                fputcsv($stream, [$name, $row['date'], $type, $row['projected'], $row['used'], $row['remaining'], $row['extras'], $row['total']], escape: '');
            }
            fclose($stream);
        }, 'meal-report-'.(Str::slug($this->eventName) ?: 'event').'.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    /** Treat user-entered names as text when opened in spreadsheet apps. */
    private function textCell(string $value): string
    {
        return preg_match('/^\s*[=+\-@]/u', $value) ? "'".$value : $value;
    }
}
