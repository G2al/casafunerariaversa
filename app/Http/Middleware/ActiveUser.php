<?php

namespace App\Http\Middleware;

use App\Filament\Resources\ServiceOrderResource;
use Closure;
use Illuminate\Http\Request;

class ActiveUser
{
    public function handle(Request $request, Closure $next)
    {
        if ($request->user() && (! $request->user()->is_active || ! $request->user()->role)) {
            abort(403, 'Account non attivo o senza ruolo.');
        }

        abort_if($request->user()?->isServiceOrderOperator() && $request->is('private/*'), 403);

        if ($request->user()?->isServiceOrderOperator()
            && $request->isMethod('GET')
            && ($request->routeIs('filament.admin.pages.dashboard') || $request->is('admin'))) {
            return redirect()->to(ServiceOrderResource::getUrl('index'));
        }

        return $next($request);
    }
}
