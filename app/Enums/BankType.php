<?php

namespace App\Enums;

enum BankType: string
{
    case Intesa  = 'intesa';
    case AIK     = 'aik';
    case OTP     = 'otp';
    case Erste   = 'erste';

    public function label(): string
    {
        return match($this) {
            self::Intesa => 'Banka Intesa',
            self::AIK    => 'AIK banka',
            self::OTP    => 'OTP banka',
            self::Erste  => 'Erste banka',
        };
    }

    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn ($case) => [$case->value => $case->label()])
            ->toArray();
    }
}
