<?php

namespace App\Filament\Auth;

use Filament\Auth\Http\Responses\Contracts\LoginResponse as LoginResponseContract;
use Filament\Facades\Filament;
use Illuminate\Http\RedirectResponse;
use Livewire\Features\SupportRedirects\Redirector;

/**
 * Filament's default LoginResponse (Filament\Auth\Http\Responses\LoginResponse)
 * always redirects to `Filament::getUrl()`, which resolves to the current
 * panel's dashboard/base path.
 *
 * The 'user' panel has no Dashboard page registered and its path is '' (root),
 * so the default response resolves to the plain homepage ('/') instead of
 * `/transactions` — regardless of any `getRedirectUrl()` override on a custom
 * Login page class, which Filament 4's Login page no longer calls.
 *
 * This override keeps the default behavior for every other panel (e.g. admin,
 * whose dashboard lives at /admin) and only special-cases the 'user' panel.
 */
class PostLoginResponse implements LoginResponseContract
{
    public function toResponse($request): RedirectResponse|Redirector
    {
        if (Filament::getCurrentPanel()?->getId() === 'user') {
            return redirect()->intended('/transactions');
        }

        return redirect()->intended(Filament::getUrl());
    }
}

