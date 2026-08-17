<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/debug-session', function () {
    return [
        'session_id' => session()->getId(),
        'user_id' => auth()->id(),
        'cookie_name' => config('session.cookie'),
        'session_exists' => session()->exists('_token'),
    ];
});