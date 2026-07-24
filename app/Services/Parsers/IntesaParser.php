<?php

namespace App\Services\Parsers;

use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\IOFactory;

class IntesaParser implements BankParserInterface
{
    private const COL_DATE        = 0;
    private const COL_TYPE        = 1;
    private const COL_DESCRIPTION = 2;
    private const COL_AMOUNT      = 3;

    private const DATA_START_ROW = 1;

    public function parse(string $filePath): Collection
    {
        $spreadsheet = IOFactory::load($filePath);
        $sheet       = $spreadsheet->getActiveSheet();
        $rows        = $sheet->toArray(null, true, true, false);

        $transactions = collect();

        foreach ($rows as $index => $row) {
            if ($index < self::DATA_START_ROW) {
                continue;
            }

            $date        = trim($row[self::COL_DATE] ?? '');
            $type        = trim($row[self::COL_TYPE] ?? '');
            $description = trim($row[self::COL_DESCRIPTION] ?? '');
            $amountRaw   = trim($row[self::COL_AMOUNT] ?? '');

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
                'raw'         => sprintf(
                    '%s|%.2f|%s',
                    $parsedDate,
                    $parsedAmount,
                    $description
                )
            ]);
        }

        return $transactions;
    }

    private function parseDate(string $value): ?string
    {
        $value = preg_replace("/[^0-9.]/", '', $value);
        $date  = \DateTime::createFromFormat('d.m.Y', $value);

        if ($date === false) {
            return null;
        }

        return $date->format('Y-m-d');
    }

    private function parseAmount(string $value): ?float
    {
        $negative = str_contains($value, '-');
        $clean = preg_replace('/[^0-9,.]/', '', $value);

        $clean = str_replace('.', '', $clean);
        $clean = str_replace(',', '.', $clean);

        if (!is_numeric($clean)) {
            return null;
        }

        $amount = (float) $clean;

        return $negative ? -$amount : $amount;
    }

    private function parseCurrency(string $value): string
    {
        if (preg_match('/([A-Z]{3})/', $value, $matches)) {
            return $matches[1];
        }

        return 'RSD';
    }
}
