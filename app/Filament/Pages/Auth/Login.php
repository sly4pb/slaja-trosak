<?php

namespace App\Filament\Pages\Auth;

use Filament\Auth\Pages\Login as BaseLogin;

/**
 * Note: Filament 4's base Login page does NOT call getRedirectUrl() anymore —
 * the post-login redirect is controlled by the LoginResponse contract
 * (see App\Filament\Auth\PostLoginResponse, bound in AppServiceProvider),
 * which is what actually sends users to /transactions after login.
 */
class Login extends BaseLogin
{
    //
}