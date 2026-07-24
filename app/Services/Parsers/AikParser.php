<?php

namespace App\Services\Parsers;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Process;
use RuntimeException;

class AikParser implements BankParserInterface
{
    /**
     * Putanja do Python skripte koja parsuje AIK PDF izvode.
     * Skripta zivi unutar kontejnera, kopirana u image prilikom build-a.
     */
    private const SCRIPT_PATH = __DIR__ . '/../../../python/aik_parser.py';

    public function parse(string $filePath): Collection
    {
        $result = Process::run([
            base_path('venv/bin/python'),
            self::SCRIPT_PATH,
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
