<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use App\Models\User;
use App\Models\Role;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;

class RegisterController extends Controller
{
    public function showRegistrationForm()
    {
        $roles = Role::where('is_default', true)->get();

        return Inertia::render('Auth/Register', [
            'roles' => $roles,
        ]);
    }

    public function register(RegisterRequest $request)
    {
        // Get default role (karyawan)
        $defaultRole = Role::where('is_default', true)->first();

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role_id' => $defaultRole->id,
            'phone' => $request->phone,
            'address' => $request->address,
            'is_active' => true,
        ]);

        event(new Registered($user));

        Auth::login($user);

        return redirect(route('dashboard'))->with('success', 'Registrasi berhasil!');
    }
}
