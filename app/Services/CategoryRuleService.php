<?php

namespace App\Services;

use App\Enums\TransactionCategory;
use App\Models\CategoryRule;
use App\Models\Transaction;
use Illuminate\Support\Collection;

class CategoryRuleService
{
    private const MIN_KEYWORD_LENGTH = 10;
    private const MAX_PREFIX_LENGTH  = 40;
    private const MAX_PAYEE_LENGTH   = 50;

    public function createOrUpdate(int $userId, string $keyword, TransactionCategory $category): int
    {
        $keyword = trim($keyword);

        if (mb_strlen($keyword) < self::MIN_KEYWORD_LENGTH) {
            return 0;
        }

        CategoryRule::updateOrCreate(
            ['user_id' => $userId, 'keyword' => $keyword],
            ['category' => $category->value]
        );

        return $this->applyRuleRetroactively($userId, $keyword, $category);
    }

    private function applyRuleRetroactively(
        int $userId,
        string $keyword,
        TransactionCategory $category
    ): int {
        return Transaction::where('user_id', $userId)
                          ->whereNull('category')
                          ->where('description', 'LIKE', '%' . $keyword . '%')
                          ->update(['category' => $category->value]);
    }

    public function applyRulesToTransactions(int $userId, Collection $transactions): Collection
    {
        $rules = CategoryRule::where('user_id', $userId)->get();

        if ($rules->isEmpty()) {
            return $transactions;
        }

        return $transactions->map(function (array $t) use ($rules) {
            if (! empty($t['category'])) {
                return $t;
            }

            $description = $t['description'] ?? '';

            $matchedRule = $rules->first(
                fn (CategoryRule $rule) => $rule->matches($description)
            );

            if ($matchedRule) {
                $t['category'] = $matchedRule->category->value;
            }

            return $t;
        });
    }

    /**
     * Izvuci keyword iz grupe opisa transakcija.
     *
     * Strategija:
     * 1. Ako opis sadrzi dugacak niz cifara (broj racuna/reference >= 10 cifara),
     *    uzimamo tekst IZA tog broja kao keyword (naziv primaoca/firme).
     *    Primjer: "Bezgotovinski prenos u RSD 160000000023860524 GENERALI OSIGURANJE"
     *             → keyword: "GENERALI OSIGURANJE"
     *
     * 2. Ako nema broja racuna, koristimo zajednicki prefiks grupe opisa
     *    (prodavnica/ATM opisi), odsjecen na granicu rijeci.
     *    Primjer: "Kupovina MP587 S. MARKOVICA CACCacak RS SLAĐAN MARJANOVIĆ"
     *             → keyword: "Kupovina MP587 S. MARKOVICA CACCacak RS"
     */
    public function extractKeywordsFromDescriptions(Collection $descriptions): Collection
    {
        $descriptions = $descriptions
            ->filter(fn ($d) => ! empty(trim($d ?? '')))
            ->map(fn ($d) => trim($d))
            ->values();

        if ($descriptions->isEmpty()) {
            return collect();
        }

        $grouped = $this->groupByCommonPrefix($descriptions);

        return $grouped->map(function (Collection $group) {
            if ($group->count() === 1) {
                return $this->extractSingleKeyword($group->first());
            }

            return $this->longestCommonPrefix($group->toArray());
        })->filter(fn ($k) => mb_strlen($k) >= self::MIN_KEYWORD_LENGTH)
                       ->unique()
                       ->values();
    }

    /**
     * Izvuci keyword iz jednog opisa.
     * Ako sadrzi broj racuna — uzmi naziv primaoca iza broja.
     * Ako ne — uzmi prefiks do MAX_PREFIX_LENGTH znakova.
     */
    private function extractSingleKeyword(string $description): string
    {
        // Trazimo dugacak niz cifara (broj racuna/reference, min 10 cifara)
        if (preg_match('/\d{10,}/', $description, $matches, PREG_OFFSET_CAPTURE)) {
            $offset      = $matches[0][1];
            $numberLen   = mb_strlen($matches[0][0]);
            $afterNumber = mb_substr($description, $offset + $numberLen);
            $payee       = trim($afterNumber);

            if (mb_strlen($payee) >= self::MIN_KEYWORD_LENGTH) {
                return $this->truncateAtWordBoundary($payee, self::MAX_PAYEE_LENGTH);
            }
        }

        // Nema broja racuna — koristi prefiks
        return $this->truncateAtWordBoundary($description, self::MAX_PREFIX_LENGTH);
    }

    /**
     * Grupise opise po zajednickom prefiksu (prvih MAX_PREFIX_LENGTH znakova).
     */
    private function groupByCommonPrefix(Collection $descriptions): Collection
    {
        $groups = collect();

        foreach ($descriptions as $desc) {
            $prefix = $this->truncateAtWordBoundary($desc, self::MAX_PREFIX_LENGTH);
            $groups->put($prefix, ($groups->get($prefix) ?? collect())->push($desc));
        }

        return $groups;
    }

    /**
     * Vraca najduzi zajednicki prefiks niza stringova,
     * odsjecen na granicu rijeci.
     */
    private function longestCommonPrefix(array $strings): string
    {
        if (empty($strings)) {
            return '';
        }

        $prefix = $strings[0];

        foreach ($strings as $string) {
            while (mb_strpos($string, $prefix) !== 0) {
                $prefix = mb_substr($prefix, 0, mb_strlen($prefix) - 1);
                if (empty($prefix)) {
                    return '';
                }
            }
        }

        return $this->truncateAtWordBoundary($prefix, mb_strlen($prefix));
    }

    /**
     * Odsjeci string na zadnjoj granici rijeci unutar max_length znakova.
     */
    private function truncateAtWordBoundary(string $text, int $maxLength): string
    {
        $text = trim($text);

        if (mb_strlen($text) <= $maxLength) {
            return $text;
        }

        $truncated = mb_substr($text, 0, $maxLength);
        $lastSpace = mb_strrpos($truncated, ' ');

        if ($lastSpace === false) {
            return $truncated;
        }

        return trim(mb_substr($truncated, 0, $lastSpace));
    }
}