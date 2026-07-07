<?php

namespace App\Services\Parsers;

use App\Enums\BankType;
use InvalidArgumentException;

class ParserFactory
{
    public static function make(BankType $bank): BankParserInterface
    {
        return match($bank) {
            BankType::Intesa => new IntesaParser(),
            BankType::AIK    => new AikParser(),

            BankType::OTP,
            BankType::Erste => throw new InvalidArgumentException(
                "Parser za banku '{$bank->label()}' jos nije implementiran."
            ),
        };
    }
}
