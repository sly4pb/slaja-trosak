<?php

namespace App\Services\Parsers;

use Illuminate\Support\Collection;

interface BankParserInterface
{
    /**
     * Parsuje uploadovani fajl i vraca kolekciju transakcija.
     *
     * Svaki element kolekcije je array sa kljucevima:
     *   - date:        string (Y-m-d)
     *   - type:        string
     *   - description: string
     *   - amount:      float  (negativan = rashod, pozitivan = prihod)
     *   - currency:    string (npr. 'RSD')
     *   - raw:         string (originalna linija, za debug)
     */
    public function parse(string $filePath): Collection;
}
