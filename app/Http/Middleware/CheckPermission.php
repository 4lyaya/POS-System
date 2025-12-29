<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckPermission
{
    public function handle(Request $request, Closure $next, $permission): Response
    {
        $user = $request->user();

        if (!$user) {
            return redirect()->route('login');
        }

        if (!$user->hasPermission($permission)) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Unauthorized. Anda tidak memiliki izin untuk akses ini.'
                ], 403);
            }

            abort(403, 'Unauthorized. Anda tidak memiliki izin untuk akses ini.');
        }

        return $next($request);
    }
}
