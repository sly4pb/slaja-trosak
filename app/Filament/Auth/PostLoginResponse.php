<?php

namespace App\Filament\Auth;

use App\Models\User;
use Filament\Auth\Http\Responses\Contracts\LoginResponse as LoginResponseContract;
use Filament\Facades\Filament;
use Illuminate\Http\RedirectResponse;
use Livewire\Features\SupportRedirects\Redirector;

/**
 * Filament's default LoginResponse always redirects to `Filament::getUrl()`
 * (the current panel's home), which is wrong here for two reasons:
 *
 * - the 'user' panel has no Dashboard page and lives at the root path, so
 *   its "home" resolves to the plain welcome page instead of /transactions
 *   (Filament 4's Login page no longer calls getRedirectUrl() — this
 *   response contract is the only redirect hook);
 * - both login forms accept cross-panel credentials (see
 *   App\Filament\Pages\Auth\Login), so the destination must depend on the
 *   account's role, not on the panel whose form happened to be used.
 *
 * Admins land on /admin, regular users on /transactions. A stored intended
 * URL is honoured only when it belongs to the panel this account can
 * actually enter — otherwise canAccessPanel() would 403 right after login.
 */
class PostLoginResponse implements LoginResponseContract
{
    public function toResponse($request): RedirectResponse|Redirector
    {
        $user = Filament::auth()->user();
        $isAdmin = $user instanceof User && $user->isAdmin();

        $intended = session()->pull('url.intended');

        if (is_string($intended) && $intended !== '') {
            $intendedPath = parse_url($intended, PHP_URL_PATH) ?: '/';
            $intendedIsAdmin = $intendedPath === '/admin' || str_starts_with($intendedPath, '/admin/');

            if ($intendedIsAdmin === $isAdmin) {
                return redirect()->to($intended);
            }
        }

        return redirect()->to($isAdmin ? url('/admin') : url('/transactions'));
    }
}
