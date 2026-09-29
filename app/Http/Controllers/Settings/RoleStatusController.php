<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\UpdateRoleStatusRequest;
use App\Models\Role;
use App\Services\RoleService;
use Illuminate\Http\RedirectResponse;

class RoleStatusController extends Controller
{
    public function __construct(private readonly RoleService $roleService) {}

    public function update(UpdateRoleStatusRequest $request, Role $role): RedirectResponse
    {
        $active = $request->boolean('active');
        $this->roleService->setActive($role, $active);

        return back()->with(
            'success',
            __($active ? 'settings.roles.toast.turned_on' : 'settings.roles.toast.turned_off'),
        );
    }
}
