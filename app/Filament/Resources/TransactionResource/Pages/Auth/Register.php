<?php

namespace App\Filament\Pages\Auth;

use App\Enums\UserRole;
use Filament\Auth\Pages\Register as BaseRegister;

class Register extends BaseRegister
{
    protected function handleRegistration(array $data): \Illuminate\Database\Eloquent\Model
    {
        // Svaki javno registrovan korisnik dobija ulogu 'user', nikad 'admin'
        $data['role'] = UserRole::User->value;

        return parent::handleRegistration($data);
    }
}
