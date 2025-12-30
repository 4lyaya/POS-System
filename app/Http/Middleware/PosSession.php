<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Session;

class PosSession
{
    public function handle(Request $request, Closure $next): Response
    {
        // Initialize POS session if not exists
        if (!$request->session()->has('pos_cart')) {
            $request->session()->put('pos_cart', []);
        }

        if (!$request->session()->has('pos_customer')) {
            $request->session()->put('pos_customer', null);
        }

        if (!$request->session()->has('pos_settings')) {
            $request->session()->put('pos_settings', [
                'tax_rate' => 11,
                'service_charge_rate' => 0,
                'enable_tax' => true,
                'enable_service_charge' => false,
            ]);
        }

        // Check if user has permission to access POS
        if (!auth()->user()->hasPermission('manage-sales')) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Unauthorized. Anda tidak memiliki izin untuk akses POS.'
                ], 403);
            }

            abort(403, 'Unauthorized. Anda tidak memiliki izin untuk akses POS.');
        }

        return $next($request);
    }
}
