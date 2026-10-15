<?php

namespace App\Http\Controllers;

use App\Http\Requests\CheckIn\IndexCheckInEntitlementsRequest;
use App\Http\Resources\CheckInEntitlementDataTableResource;
use App\Models\ArtistEngagement;
use App\Models\TeamEngagement;
use App\Models\VendorEngagement;
use App\Queries\CheckInEntitlementsQuery;
use App\Support\EventContext;

class CheckInEntitlementDataTableController extends Controller
{
    public function index(IndexCheckInEntitlementsRequest $request, EventContext $context, CheckInEntitlementsQuery $query): CheckInEntitlementDataTableResource
    {
        $event = $context->requireCurrent($request->user());
        $data = $request->validated();
        $model = match ($data['type']) {
            'artist' => ArtistEngagement::class, 'vendor' => VendorEngagement::class, 'team' => TeamEngagement::class,
        };
        $engagement = $model::query()->where('event_id', $event->id)
            ->where('status', $data['type'] === 'team' ? 'hired' : 'confirmed')->findOrFail($data['engagement_id']);
        abort_unless($data['type'] === 'team'
            ? $engagement->person_id === (int) $data['person_id']
            : $engagement->people()->where('people.id', $data['person_id'])->exists(), 404);

        return new CheckInEntitlementDataTableResource(['draw' => (int) $data['draw'], ...$query->dataTable($event, $data)]);
    }
}
