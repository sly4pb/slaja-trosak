<?php

namespace App\Services\Parsers;

use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\IOFactory;

class IntesaParser implements BankParserInterface
{
    // Intesa kolone (0-indeksirane)
    private const COL_DATE        = 0;
    private const COL_TYPE        = 1;
    private const COL_DESCRIPTION = 2;
    private const COL_AMOUNT      = 3;

    // Red od kojeg pocinje data (preskacemo header)
    private const DATA_START_ROW = 1;

    public function parse(string $filePath): Collection
    {
        $spreadsheet = IOFactory::load($filePath);
        $sheet       = $spreadsheet->getActiveSheet();
        $rows        = $sheet->toArray(null, true, true, false);

        $transactions = collect();

        foreach ($rows as $index => $row) {
            // Preskoci header i prazne redove
            if ($index < self::DATA_START_ROW) {
                continue;
            }

            $date        = trim($row[self::COL_DATE] ?? '');
            $type        = trim($row[self::COL_TYPE] ?? '');
            $description = trim($row[self::COL_DESCRIPTION] ?? '');
            $amountRaw   = trim($row[self::COL_AMOUNT] ?? '');

            // Preskoci ako nema datuma ili iznosa
            if (empty($date) || empty($amountRaw)) {
                continue;
            }

            $parsedDate   = $this->parseDate($date);
            $parsedAmount = $this->parseAmount($amountRaw);
            $currency     = $this->parseCurrency($amountRaw);

            if ($parsedDate === null || $parsedAmount === null) {
                continue;
            }

            $transactions->push([
                'date'        => $parsedDate,
                'type'        => $type ?: 'Ostalo',
                'description' => $description,
                'amount'      => $parsedAmount,
                'currency'    => $currency,
                'raw'         => implode(' | ', array_filter([$date, $type, $description, $amountRaw])),
            ]);
        }

        return $transactions;
    }

    /**
     * Parsuje datum formata "dd.mm.yyyy" u "Y-m-d"
     */
    private function parseDate(string $value): ?string
    {
        // Ocisti visak znakova (apostrofi, razmaci)
        $value = preg_replace("/[^0-9.]/", '', $value);

        $date = \DateTime::createFromFormat('d.m.Y', $value);

        if ($date === false) {
            return null;
        }

        return $date->format('Y-m-d');
    }

    /**
     * Parsuje iznos npr. "- 656,45 RSD" ili "+ 50.000,00 RSD" u float
     */
    private function parseAmount(string $value): ?float
    {
        // Odredi predznak
        $negative = str_contains($value, '-');

        // Ukloni sve osim cifara, zareza i tacke
        $clean = preg_replace('/[^0-9,.]/', '', $value);

        // Intesa format: tacka je separator hiljada, zarez je decimalni
        // npr. "50.000,00" -> "50000.00"
        $clean = str_replace('.', '', $clean);   // ukloni separator hiljada
        $clean = str_replace(',', '.', $clean);  // zamijeni decimalni zarez sa tackom

        if (!is_numeric($clean)) {
            return null;
        }

        $amount = (float) $clean;

        return $negative ? -$amount : $amount;
    }

    /**
     * Izvuce valutu iz stringa npr. "- 656,45 RSD" -> "RSD"
     */
    private function parseCurrency(string $value): string
    {
        if (preg_match('/([A-Z]{3})/', $value, $matches)) {
            return $matches[1];
        }

        return 'RSD'; // default za Intesa Srbija
    }
}
