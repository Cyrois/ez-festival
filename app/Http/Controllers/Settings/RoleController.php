<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\DestroyRoleRequest;
use App\Http\Requests\Settings\IndexRoleDataTableRequest;
use App\Http\Requests\Settings\IndexRolePeopleRequest;
use App\Http\Requests\Settings\IndexRolesRequest;
use App\Http\Requests\Settings\StoreRoleRequest;
use App\Http\Requests\Settings\UpdateRoleRequest;
use App\Http\Resources\RoleDataTableResource;
use App\Http\Resources\RolePeopleDataTableResource;
use App\Http\Resources\RoleResource;
use App\Models\Role;
use App\Repositories\RoleRepository;
use App\Services\RoleService;
use App\Support\Permissions;
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
        $status = $request->validated('status', RoleRepository::STATUS_ALL);

        return Inertia::render('Settings/Roles', [
            'roles' => RoleResource::collection($this->roles->paginate($search, $status)),
            'filters' => [
                'search' => $search,
                'status' => $status,
            ],
            'hasAnyRoles' => $this->roles->exists(),
            'canManageRoles' => $request->user()->can('manage-roles'),
        ]);
    }

    public function dataTable(IndexRoleDataTableRequest $request): RoleDataTableResource
    {
        $result = $this->roles->dataTable(
            trim((string) $request->validated('query', '')),
            $request->validated('status', RoleRepository::STATUS_ALL),
            (int) $request->validated('start'),
            (int) $request->validated('length'),
            $request->validated('order.0.dir', 'asc'),
        );

        return new RoleDataTableResource([
            'draw' => (int) $request->validated('draw'),
            ...$result,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Settings/Roles/Create', ['permissionGroups' => Permissions::groups()]);
    }

    public function people(IndexRolePeopleRequest $request, Role $role): RolePeopleDataTableResource
    {
        return new RolePeopleDataTableResource([
            'draw' => (int) $request->validated('draw'),
            ...$this->roles->peopleDataTable(
                $role,
                trim((string) $request->validated('search.value', '')),
                (int) $request->validated('start'),
                (int) $request->validated('length'),
                $request->validated('order.0.dir', 'asc'),
            ),
        ]);
    }

    public function edit(Role $role): Response
    {
        $role->loadCount(['teamEngagements as people_count' => fn ($query) => $query->selectRaw('count(distinct person_id)')]);

        return Inertia::render('Settings/Roles/Edit', [
            'role' => (new RoleResource($role))->resolve(),
            'permissionGroups' => Permissions::groups(),
        ]);
    }

    public function destroy(DestroyRoleRequest $request, Role $role): RedirectResponse
    {
        $this->roleService->delete($role);

        return redirect()->route('settings.roles')->with('success', __('settings.roles.toast.deleted'));
    }

    public function store(StoreRoleRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $this->roleService->create(
            $data['name'],
            $data['permissions'],
        );

        return redirect()->route('settings.roles')->with('success', __('settings.roles.toast.created'));
    }

    public function update(UpdateRoleRequest $request, Role $role): RedirectResponse
    {
        $data = $request->validated();
        $this->roleService->update(
            $role,
            $data['name'],
            $data['permissions'],
        );

        return redirect()->route('settings.roles')->with('success', __('settings.roles.toast.updated'));
    }
}
