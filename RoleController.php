<?php

namespace App\Http\Controllers\Dashboard;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use App\Http\Controllers\Controller;
use Spatie\Permission\Models\Permission;
use App\Http\Requests\Admin\Roles\StoreRequest;
use App\Http\Requests\Admin\Roles\UpdateRequest;
use App\Http\Requests\Dashboard\Role\RoleStoreRequest;
use App\Http\Requests\Dashboard\Role\RoleUpdateRequest;

class RoleController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $roles = Role::latest()->paginate(3);
        return view('dashboard.pages.roles.index',compact('roles'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $permissions = Permission::all();
        return view('dashboard.pages.roles.create',compact('permissions'));

    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(RoleStoreRequest $request)
    {
        $data= $request->validated();
        $role = Role::create($data);
        $permissions = $request->permissions_id;
        $role->givePermissionTo($permissions);
        return redirect()->route('Admin.roles.index');

    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Role $role)
    {
        $permissions = Permission::all();
        return view('dashboard.pages.roles.edit',compact('role','permissions'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(RoleUpdateRequest $request, Role $role)
    {
        $validated = $request->validated();

        if ($role->name !== $validated['name']) {
            $role->update(['name' => $validated['name']]);
        }

        if ($request->has('permissions_id')) {
            $role->syncPermissions($validated['permissions_id']);
        }

        return redirect()->route('Admin.roles.index')->with('success', __('Role updated successfully!'));
    }


    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Role $role)
    {
        $role->delete();
        return redirect()->route('Admin.roles.index');

    }
}
