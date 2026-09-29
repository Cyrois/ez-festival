<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\IndexRoleDataTableRequest;
use App\Http\Requests\Settings\IndexRolesRequest;
use App\Http\Requests\Settings\StoreRoleRequest;
use App\Http\Requests\Settings\UpdateRoleRequest;
use App\Http\Resources\RoleDataTableResource;
use App\Http\Resources\RoleResource;
use App\Models\Role;
use App\Repositories\RoleRepository;
use App\Services\RoleService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class RoleController extends Controller
{
    public function __construct(
        private readonly RoleRepository $roles,
        private readonly RoleService $roleService,
    ) {}

    public function index(IndexRolesRequest $request): Response
    {
        $search = trim((string) $request->validated('search', ''));
        $status = $request->validated('status', RoleRepository::STATUS_ON);

        return Inertia::render('Settings/Roles', [
            'roles' => RoleResource::collection($this->roles->paginate($search, $status)),
            'filters' => [
                'search' => $search,
                'status' => $status,
            ],
            'hasAnyRoles' => $this->roles->exists(),
        ]);
    }

    public function dataTable(IndexRoleDataTableRequest $request): RoleDataTableResource
    {
        $result = $this->roles->dataTable(
            trim((string) $request->validated('query', '')),
            $request->validated('status', RoleRepository::STATUS_ON),
            (int) $request->validated('start'),
            (int) $request->validated('length'),
            $request->validated('order.0.dir', 'asc'),
        );

        return new RoleDataTableResource([
            'draw' => (int) $request->validated('draw'),
            ...$result,
        ]);
    }

    public function store(StoreRoleRequest $request): RedirectResponse
    {
        $this->roleService->create($request->validated('name'));

        return back()->with('success', __('settings.roles.toast.created'));
    }

    public function update(UpdateRoleRequest $request, Role $role): RedirectResponse
    {
        $this->roleService->rename($role, $request->validated('name'));

        return back()->with('success', __('settings.roles.toast.renamed'));
    }
}
