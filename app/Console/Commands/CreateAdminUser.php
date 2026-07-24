<?php

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class CreateAdminUser extends Command
{
    protected $signature   = 'make:admin
                                {--name= : Ime admina}
                                {--email= : Email admina}
                                {--password= : Lozinka admina}';

    protected $description = 'Kreira novog admin korisnika';

    public function handle(): int
    {
        $name     = $this->option('name')     ?? $this->ask('Unesite ime');
        $email    = $this->option('email')    ?? $this->ask('Unesite email');
        $password = $this->option('password') ?? $this->secret('Unesite lozinku');

        if (User::where('email', $email)->exists()) {
            $this->error("Korisnik sa emailom '{$email}' već postoji.");
            return self::FAILURE;
        }

        $user = User::create([
            'name'     => $name,
            'email'    => $email,
            'password' => Hash::make($password),
            'role'     => UserRole::Admin->value,
        ]);

        $this->info("Admin korisnik '{$user->name}' ({$user->email}) uspješno kreiran.");

        return self::SUCCESS;
    }
}

