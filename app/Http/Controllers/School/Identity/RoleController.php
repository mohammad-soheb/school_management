<?php

namespace App\Http\Controllers\School\Identity;

use App\Http\Controllers\Controller;
use App\Http\Requests\Identity\UpdateRolePermissionsRequest;
use App\Models\Identity\Role;
use App\Services\Identity\RoleService;
use App\Support\Identity\Permissions;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

/** View and edit what each role in the school can do (FR-103). */
class RoleController extends Controller
{
    public function __construct(private readonly RoleService $roles) {}

    public function index(): View
    {
        return view('school.access.roles', ['roles' => Role::query()->withCount('permissions')->orderBy('name')->get()]);
    }

    public function edit(Role $role): View
    {
        return view('school.access.role-edit', [
            'role' => $role,
            'groups' => Permissions::grouped(),
            'granted' => $role->permissionNames(),
        ]);
    }

    public function update(UpdateRolePermissionsRequest $request, Role $role): RedirectResponse
    {
        $this->roles->syncPermissions($role, $request->validated('permissions') ?? [], $request->user());

        return redirect()->route('school.access.roles.index')->with('status', "Permissions for {$role->name} saved.");
    }
}
