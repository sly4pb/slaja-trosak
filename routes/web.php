<?php

use Illuminate\Support\Facades\Route;

// Role-aware entry point: guests go to the login form, authenticated users
// land in their own panel (see also App\Filament\Auth\PostLoginResponse).
Route::get('/', function () {
    $user = auth()->user();

    if (! $user) {
        return redirect('/login');
    }

    return redirect($user->isAdmin() ? '/admin' : '/transactions');
});
