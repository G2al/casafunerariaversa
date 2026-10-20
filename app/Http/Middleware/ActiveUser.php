<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class ActiveUser
{
    public function handle(Request $request, Closure $next)
    {
        if ($request->user() && (! $request->user()->is_active || ! $request->user()->role)) {
            abort(403, 'Account non attivo o senza ruolo.');
        }

        return $next($request);
    }
}
