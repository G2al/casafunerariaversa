<?php

namespace App\Http\Responses;

use App\Filament\Resources\ServiceOrderResource;
use Filament\Facades\Filament;
use Illuminate\Http\RedirectResponse;
use Livewire\Features\SupportRedirects\Redirector;

class LoginResponse extends \Filament\Auth\Http\Responses\LoginResponse
{
    public function toResponse($request): RedirectResponse|Redirector
    {
        if (Filament::auth()->user()?->isServiceOrderOperator()) {
            // Il login standard privilegia l'URL precedente, anche se il nuovo
            // ruolo non può aprirlo. Gli ordini sono l'unica destinazione ammessa.
            $request->session()->forget('url.intended');

            return redirect()->to(ServiceOrderResource::getUrl('index'));
        }

        return parent::toResponse($request);
    }
}
