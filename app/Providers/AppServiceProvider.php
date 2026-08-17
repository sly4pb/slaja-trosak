<?php

namespace App\Providers;

use App\Filament\Auth\PostLoginResponse;
use App\Filament\Auth\PostRegistrationResponse;
use Filament\Auth\Http\Responses\Contracts\LoginResponse;
use Filament\Auth\Http\Responses\Contracts\RegistrationResponse;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Redirect the 'user' panel's login to /transactions instead of the
        // default Filament::getUrl() fallback (which resolves to '/' since
        // the 'user' panel has no Dashboard page). See PostLoginResponse for
        // details.
        $this->app->bind(LoginResponse::class, PostLoginResponse::class);

        // Same problem for registration: the default RegistrationResponse also
        // falls back to Filament::getUrl(). Every self-registered account gets
        // the 'user' role, so always land it on /transactions (registering via
        // /admin/register would otherwise 403 through canAccessPanel()). See
        // PostRegistrationResponse for details.
        $this->app->bind(RegistrationResponse::class, PostRegistrationResponse::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
