<?php

namespace App\Services\Parsers;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Process;
use RuntimeException;

class OtpParser implements BankParserInterface
{
    /**
     * Putanja do Python skripte koja parsuje OTP PDF izvode.
     * Skripta zivi unutar kontejnera, kopirana u image prilikom build-a.
     */
    private const SCRIPT_PATH = '/var/www/html/python/otp_parser.py';

    public function parse(string $filePath): Collection
    {
        $result = Process::run(['python3', self::SCRIPT_PATH, $filePath]);

        if ($result->failed()) {
            throw new RuntimeException(
                'OTP PDF parser skripta nije uspela da se izvrsi: ' . $result->errorOutput()
            );
        }

        $output = trim($result->output());
        $data   = json_decode($output, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new RuntimeException(
                'OTP PDF parser je vratio nevalidan JSON: ' . substr($output, 0, 500)
            );
        }

        if (! ($data['success'] ?? false)) {
            throw new RuntimeException(
                'Greška pri parsiranju OTP PDF izvoda: ' . ($data['error'] ?? 'nepoznata greška')
            );
        }

        return collect($data['transactions'] ?? []);
    }
}
