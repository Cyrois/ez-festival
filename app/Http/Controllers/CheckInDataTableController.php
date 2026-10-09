<?php

namespace App\Http\Controllers;

use App\Http\Requests\CheckIn\IndexCheckInDataTableRequest;
use App\Http\Resources\CheckInDataTableResource;
use App\Queries\CheckInPeopleQuery;
use App\Support\EventContext;

class CheckInDataTableController extends Controller
{
    public function index(IndexCheckInDataTableRequest $request, EventContext $context, CheckInPeopleQuery $query): CheckInDataTableResource
    {
        $event = $context->requireCurrent($request->user());
        $data = $request->validated();
        $result = $query->dataTable(
            $event->id,
            isset($data['pass']) ? (int) $data['pass'] : null,
            trim($data['search'] ?? ''),
            $data['status'] ?? 'all',
            $data['type'] ?? 'all',
            (int) $data['start'],
            (int) $data['length'],
        );
        $canEdit = [
            'artist' => $request->user()->can('artists.edit', $event),
            'vendor' => $request->user()->can('vendors.edit', $event),
        ];
        foreach ($result['people'] as $person) {
            $person->can_edit = $canEdit[$person->type] ?? false;
        }

        return new CheckInDataTableResource(['draw' => (int) $data['draw'], ...$result]);
    }
}
