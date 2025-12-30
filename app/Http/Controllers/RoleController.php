<?php

namespace App\Http\Controllers;

use App\Models\Role;
use Illuminate\Http\Request;
use Inertia\Inertia;

class RoleController extends Controller
{
    public function __construct()
    {
        $this->middleware('role:admin');
    }

    public function index(Request $request)
    {
        $roles = Role::query()
            ->when($request->search, function ($query, $search) {
                $query->where('name', 'like', "%{$search}%")
                    ->orWhere('slug', 'like', "%{$search}%");
            })
            ->orderBy('name')
            ->paginate(20);

        return Inertia::render('Roles/Index', [
            'roles' => $roles,
            'filters' => $request->only(['search']),
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255|unique:roles',
            'slug' => 'required|string|max:255|unique:roles',
            'description' => 'nullable|string|max:500',
            'permissions' => 'required|array',
            'permissions.*' => 'string',
            'is_default' => 'boolean',
        ]);

        try {
            Role::create([
                'name' => $request->name,
                'slug' => $request->slug,
                'description' => $request->description,
                'permissions' => $request->permissions,
                'is_default' => $request->boolean('is_default', false),
            ]);

            return redirect()->route('roles.index')
                ->with('success', 'Role berhasil ditambahkan');
        } catch (\Exception $e) {
            return redirect()->back()
                ->withInput()
                ->with('error', 'Gagal menambahkan role: ' . $e->getMessage());
        }
    }

    public function update(Request $request, Role $role)
    {
        $request->validate([
            'name' => 'required|string|max:255|unique:roles,name,' . $role->id,
            'slug' => 'required|string|max:255|unique:roles,slug,' . $role->id,
            'description' => 'nullable|string|max:500',
            'permissions' => 'required|array',
            'permissions.*' => 'string',
            'is_default' => 'boolean',
        ]);

        try {
            $role->update([
                'name' => $request->name,
                'slug' => $request->slug,
                'description' => $request->description,
                'permissions' => $request->permissions,
                'is_default' => $request->boolean('is_default', false),
            ]);

            return redirect()->route('roles.index')
                ->with('success', 'Role berhasil diperbarui');
        } catch (\Exception $e) {
            return redirect()->back()
                ->withInput()
                ->with('error', 'Gagal memperbarui role: ' . $e->getMessage());
        }
    }

    public function destroy(Role $role)
    {
        try {
            // Check if role is used by users
            if ($role->users()->exists()) {
                return redirect()->back()
                    ->with('error', 'Role tidak dapat dihapus karena memiliki user terkait');
            }

            // Prevent deleting default role
            if ($role->is_default) {
                return redirect()->back()
                    ->with('error', 'Role default tidak dapat dihapus');
            }

            $role->delete();

            return redirect()->route('roles.index')
                ->with('success', 'Role berhasil dihapus');
        } catch (\Exception $e) {
            return redirect()->back()
                ->with('error', 'Gagal menghapus role: ' . $e->getMessage());
        }
    }
}
