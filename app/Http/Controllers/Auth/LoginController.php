<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class LoginController extends Controller
{
    public function showLoginForm()
    {
        return Inertia::render('Auth/Login');
    }

    public function login(LoginRequest $request)
    {
        $credentials = $request->validated();

        if (Auth::attempt($credentials, $request->boolean('remember'))) {
            $request->session()->regenerate();

            // Check if user is active
            if (!Auth::user()->is_active) {
                Auth::logout();
                throw ValidationException::withMessages([
                    'email' => 'Akun Anda dinonaktifkan. Silakan hubungi administrator.',
                ]);
            }

            // Redirect based on role
            return $this->authenticated($request, Auth::user());
        }

        throw ValidationException::withMessages([
            'email' => 'Email atau password salah.',
        ]);
    }

    protected function authenticated(Request $request, $user)
    {
        // Check user role and redirect accordingly
        if ($user->isAdmin() || $user->isOperator()) {
            return redirect()->intended(route('dashboard'));
        } elseif ($user->isCashier()) {
            return redirect()->intended(route('sales.create'));
        }

        return redirect()->intended(route('dashboard'));
    }
}
