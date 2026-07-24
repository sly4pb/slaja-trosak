<?php

namespace App\Filament\Pages\Auth;

use App\Enums\UserRole;
use Filament\Auth\Pages\Register as BaseRegister;

class Register extends BaseRegister
{
    protected function handleRegistration(array $data): \Illuminate\Database\Eloquent\Model
    {
        $data['role'] = UserRole::User->value;

        return parent::handleRegistration($data);
    }
}

