<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\ReorderTypeRequest;
use App\Http\Requests\Settings\StoreTypeRequest;
use App\Http\Requests\Settings\UpdateTypeRequest;
use App\Models\ArtistType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class ArtistTypeController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Settings/ArtistTypes', [
            'types' => ArtistType::query()
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get(['id', 'name', 'sort_order']),
        ]);
    }

    public function reorder(ReorderTypeRequest $request): RedirectResponse
    {
        DB::transaction(function () use ($request): void {
            foreach ($request->validated('types') as $type) {
                ArtistType::query()
                    ->whereKey($type['id'])
                    ->update(['sort_order' => $type['position']]);
            }
        });

        return back();
    }

    public function store(StoreTypeRequest $request): RedirectResponse
    {
        DB::transaction(function () use ($request): void {
            ArtistType::query()->create([
                'name' => $request->validated('name'),
                'sort_order' => 0,
            ]);

            foreach ($request->validated('types', []) as $item) {
                ArtistType::query()
                    ->whereKey($item['id'])
                    ->update(['sort_order' => $item['position'] + 1]);
            }
        });

        return back();
    }

    public function update(UpdateTypeRequest $request, ArtistType $artistType): RedirectResponse
    {
        $artistType->update($request->validated());

        return back();
    }

    public function destroy(ArtistType $artistType): RedirectResponse
    {
        $artistType->delete();

        return back();
    }
}
