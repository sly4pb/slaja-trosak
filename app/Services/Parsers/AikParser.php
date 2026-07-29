<?php

namespace App\Services\Parsers;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Process;
use RuntimeException;

class AikParser implements BankParserInterface
{
    /**
     * Path to the Python script that parses AIK PDF statements.
     * Uses base_path() so it resolves correctly regardless of deployment
     * location (Docker at /var/www/html, bare server at /var/www/whatever, etc).
     */
    private function scriptPath(): string
    {
        return base_path('python/aik_parser.py');
    }

    public function parse(string $filePath): Collection
    {
        $result = Process::run([
            config('services.python.binary'),
            $this->scriptPath(),
            $filePath
        ]);

        if ($result->failed()) {
            throw new RuntimeException(
                'AIK PDF parser skripta nije uspela da se izvrsi: ' . $result->errorOutput()
            );
        }

        $output = trim($result->output());
        $data   = json_decode($output, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new RuntimeException(
                'AIK PDF parser je vratio nevalidan JSON: ' . substr($output, 0, 500)
            );
        }

        if (! ($data['success'] ?? false)) {
            throw new RuntimeException(
                'Greška pri parsiranju AIK PDF izvoda: ' . ($data['error'] ?? 'nepoznata greška')
            );
        }

        return collect($data['transactions'] ?? []);
    }
}
