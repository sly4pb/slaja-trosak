<?php

namespace App\Services\Parsers;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Process;
use RuntimeException;

class OtpParser implements BankParserInterface
{
    /**
     * Path to the Python script that parses AIK PDF statements.
     * Uses base_path() so it resolves correctly regardless of deployment
     * location (Docker at /var/www/html, bare server at /var/www/whatever, etc).
     */
    private function scriptPath(): string
    {
        return base_path('python/otp_parser.py');
    }

    public function parse(string $filePath): Collection
    {
        $result = Process::run([
            base_path('venv/bin/python'),
            $this->scriptPath(),
            $filePath
        ]);

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
