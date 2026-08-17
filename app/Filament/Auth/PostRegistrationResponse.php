<?php

namespace App\Filament\Auth;

use Filament\Auth\Http\Responses\Contracts\RegistrationResponse as RegistrationResponseContract;
use Illuminate\Http\RedirectResponse;
use Livewire\Features\SupportRedirects\Redirector;

/**
 * Filament's default RegistrationResponse redirects to `Filament::getUrl()`,
 * which resolves to '/' (the plain welcome page) for the 'user' panel and to
 * '/admin' for the admin panel.
 *
 * Both are wrong here: every self-registered account gets the 'user' role
 * (see App\Filament\Pages\Auth\Register), so a redirect into the admin panel
 * would immediately 403 via User::canAccessPanel(), and a redirect to '/'
 * strands the user outside the app.
 *
 * Always send freshly registered users to /transactions in the user panel,
 * regardless of which panel's registration page they used — both panels share
 * the 'web' auth guard, so the new session is valid for the user panel.
 */
class PostRegistrationResponse implements RegistrationResponseContract
{
    public function toResponse($request): RedirectResponse|Redirector
    {
        return redirect()->to('/transactions');
    }
}
