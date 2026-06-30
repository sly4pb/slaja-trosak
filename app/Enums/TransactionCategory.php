<?php

namespace App\Enums;

enum TransactionCategory: string
{
    case HouseBills  = 'house_bills';
    case Food        = 'food';
    case Pharmacy    = 'pharmacy';
    case Car         = 'car';
    case Clothes     = 'clothes';
    case Restaurants = 'restaurants';
    case Gym         = 'gym';
    case Gifts       = 'gifts';
    case Travel      = 'travel';
    case Other       = 'other';

    public function label(): string
    {
        return match($this) {
            self::HouseBills  => 'House Monthly Bills',
            self::Food        => 'Food',
            self::Pharmacy    => 'Pharmacy',
            self::Car         => 'Car',
            self::Clothes     => 'Clothes',
            self::Restaurants => 'Restaurants',
            self::Gym         => 'Gym',
            self::Gifts       => 'Gifts',
            self::Travel      => 'Travel',
            self::Other       => 'Other',
        };
    }

    public function color(): string
    {
        return match($this) {
            self::HouseBills  => 'warning',
            self::Food        => 'success',
            self::Pharmacy    => 'danger',
            self::Car         => 'info',
            self::Clothes     => 'purple',
            self::Restaurants => 'pink',
            self::Gym         => 'cyan',
            self::Gifts       => 'rose',
            self::Travel      => 'indigo',
            self::Other       => 'gray',
        };
    }

    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn ($case) => [$case->value => $case->label()])
            ->toArray();
    }
}
