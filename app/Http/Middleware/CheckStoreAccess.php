<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Models\Setting;

class CheckStoreAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        // Check if store is in maintenance mode
        $maintenanceMode = Setting::getValue('maintenance_mode', false);

        if ($maintenanceMode && !auth()->user()->isAdmin()) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Sistem sedang dalam pemeliharaan. Silakan coba lagi nanti.'
                ], 503);
            }

            return response()->view('errors.maintenance', [], 503);
        }

        // Check store opening hours (if implemented)
        $openingHour = Setting::getValue('opening_hour', '08:00');
        $closingHour = Setting::getValue('closing_hour', '21:00');

        $currentTime = now()->format('H:i');

        if ($currentTime < $openingHour || $currentTime > $closingHour) {
            if (!auth()->user()->isAdmin()) {
                if ($request->expectsJson()) {
                    return response()->json([
                        'message' => 'Toko sedang tutup. Jam operasional: ' . $openingHour . ' - ' . $closingHour
                    ], 403);
                }

                return redirect()->route('dashboard')
                    ->with('warning', 'Toko sedang tutup. Jam operasional: ' . $openingHour . ' - ' . $closingHour);
            }
        }

        return $next($request);
    }
}
