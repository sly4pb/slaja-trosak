<?php

namespace App\Services;

use App\Enums\BankType;
use App\Models\BankUpload;
use App\Models\Transaction;
use App\Services\Parsers\ParserFactory;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

class BankUploadService
{
    public function __construct(
        private readonly CategoryRuleService $categoryRuleService
    ) {}

    public function handle(UploadedFile $file, BankType $bank, int $userId): BankUpload
    {
        $storedPath = $file->store("bank-uploads/{$userId}", 'private');

        $upload = BankUpload::create([
            'user_id'           => $userId,
            'bank'              => $bank,
            'original_filename' => $file->getClientOriginalName(),
            'stored_path'       => $storedPath,
            'status'            => 'processing',
        ]);

        try {
            $parser       = ParserFactory::make($bank);
            $absolutePath = Storage::disk('private')->path($storedPath);
            $transactions = $parser->parse($absolutePath);

            $existingKeys = array_fill_keys(
                Transaction::query()
                           ->where('user_id', $userId)
                           ->where('bank', $bank->value)
                           ->selectRaw('CONCAT(DATE(date), "|", amount, "|", IFNULL(description, "")) as dup_key')
                           ->pluck('dup_key')
                           ->toArray(),
                true
            );

            $newTransactions = $transactions->reject(function (array $t) use ($existingKeys) {
                return isset($existingKeys[$t['raw']]);
            });

            $skipped = $transactions->count() - $newTransactions->count();

            $newTransactions = $this->categoryRuleService->applyRulesToTransactions(
                $userId,
                $newTransactions
            );

            DB::transaction(function () use ($newTransactions, $upload, $userId, $bank) {
                if ($newTransactions->isEmpty()) {
                    $upload->update([
                        'status'             => 'done',
                        'transactions_count' => 0,
                    ]);
                    return;
                }

                $now   = now();
                $chunk = $newTransactions->map(fn ($t) => [
                    'user_id'          => $userId,
                    'bank_upload_id'   => $upload->id,
                    'bank'             => $bank->value,
                    'date'             => $t['date'],
                    'transaction_date' => $t['date'],
                    'type'             => $t['type'],
                    'category'         => $t['category'] ?? null,
                    'description'      => $t['description'],
                    'amount'           => $t['amount'],
                    'currency'         => $t['currency'],
                    'raw'              => $t['raw'],
                    'created_at'       => $now,
                    'updated_at'       => $now,
                ])->values()->toArray();

                Transaction::insert($chunk);

                $upload->update([
                    'status'             => 'done',
                    'transactions_count' => count($chunk),
                ]);
            });

            if ($skipped > 0) {
                $upload->update([
                    'error_message' => "Skipped {$skipped} duplicate transaction(s).",
                ]);
            }
        } catch (Throwable $e) {
            $upload->update([
                'status'        => 'failed',
                'error_message' => $e->getMessage(),
            ]);
        }

        return $upload->fresh();
    }
}