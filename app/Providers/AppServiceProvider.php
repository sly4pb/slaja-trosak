<?php

namespace App\Providers;

use App\Filament\Auth\PostLoginResponse;
use Filament\Auth\Http\Responses\Contracts\LoginResponse;
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
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
