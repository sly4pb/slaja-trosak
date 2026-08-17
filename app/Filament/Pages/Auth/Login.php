<?php

namespace App\Filament\Pages\Auth;

use Filament\Auth\Http\Responses\Contracts\LoginResponse;
use Filament\Auth\Pages\Login as BaseLogin;
use Filament\Facades\Filament;
use Filament\Models\Contracts\FilamentUser;
use Illuminate\Validation\ValidationException;

/**
 * Shared login page for both panels.
 *
 * Filament's base page rejects valid credentials whenever the account cannot
 * access the panel whose form was used (an admin on /login, a regular user
 * on /admin/login). Both panels share the same 'web' guard, so instead of
 * failing we complete the login and let PostLoginResponse send each role to
 * its own panel (admin → /admin, user → /transactions). Panel *entry* stays
 * protected by User::canAccessPanel() via Filament's Authenticate middleware.
 *
 * Note: Filament 4 no longer calls getRedirectUrl() — the post-login
 * redirect is fully controlled by the LoginResponse contract
 * (App\Filament\Auth\PostLoginResponse, bound in AppServiceProvider).
 */
class Login extends BaseLogin
{
    public function authenticate(): ?LoginResponse
    {
        try {
            return parent::authenticate();
        } catch (ValidationException $exception) {
            $data = $this->form->getState();
            $provider = Filament::auth()->getProvider();
            $credentials = $this->getCredentialsFromFormData($data);

            $user = $provider->retrieveByCredentials($credentials);

            if ((! $user) || (! $provider->validateCredentials($user, $credentials))) {
                throw $exception;
            }

            $accessiblePanels = array_filter(
                Filament::getPanels(),
                fn ($panel) => (! $user instanceof FilamentUser) || $user->canAccessPanel($panel),
            );

            if ($accessiblePanels === []) {
                throw $exception;
            }

            Filament::auth()->login($user, $data['remember'] ?? false);
            session()->regenerate();

            return app(LoginResponse::class);
        }
    }
}
