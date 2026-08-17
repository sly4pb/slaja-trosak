<?php

namespace App\Services;

use App\Models\ProductPriceHistory;
use App\Models\TrackedProduct;
use Illuminate\Support\Facades\Process;

class PriceCheckService
{
    private function scriptPath(): string
    {
        return base_path('python/price_scraper.py');
    }

    /**
     * Check the current price for a single tracked product.
     * Updates the product record and logs price history if the check succeeds.
     *
     * @return array{changed: bool, oldPrice: ?float, newPrice: ?float}
     */
    public function check(TrackedProduct $product): array
    {
        // A hung site throws ProcessTimedOutException — mark this product as
        // failed instead of letting the exception abort the whole batch run.
        try {
            $result = Process::timeout(30)->run(['python3', $this->scriptPath(), $product->url]);
        } catch (\Throwable $e) {
            $product->update([
                'status'          => 'failed',
                'error_message'   => 'Scraper process failed: ' . substr($e->getMessage(), 0, 300),
                'last_checked_at' => now(),
            ]);

            return ['changed' => false, 'oldPrice' => null, 'newPrice' => null];
        }

        $output = trim($result->output());
        $data   = json_decode($output, true);

        if (json_last_error() !== JSON_ERROR_NONE || $data === null) {
            $product->update([
                'status'          => 'failed',
                'error_message'   => 'Invalid scraper output: ' . substr(trim($output . ' ' . $result->errorOutput()), 0, 300),
                'last_checked_at' => now(),
            ]);

            return ['changed' => false, 'oldPrice' => null, 'newPrice' => null];
        }

        if (! ($data['success'] ?? false)) {
            $product->update([
                'status'          => 'failed',
                'error_message'   => $data['error'] ?? 'Unknown scraping error',
                'last_checked_at' => now(),
            ]);

            // Still update product name if we managed to extract it despite price failure
            if (! empty($data['product_name']) && empty($product->product_name)) {
                $product->update(['product_name' => $data['product_name']]);
            }

            return ['changed' => false, 'oldPrice' => null, 'newPrice' => null];
        }

        $newPrice = (float) $data['price'];
        $oldPrice = $product->current_price !== null ? (float) $product->current_price : null;
        $changed  = $oldPrice !== null && abs($oldPrice - $newPrice) > 0.01;

        $product->update([
            'product_name'    => $data['product_name'] ?? $product->product_name,
            'current_price'   => $newPrice,
            'currency'        => $data['currency'] ?? 'RSD',
            'status'          => 'ok',
            'error_message'   => null,
            'last_checked_at' => now(),
        ]);

        ProductPriceHistory::create([
            'tracked_product_id' => $product->id,
            'price'              => $newPrice,
            'checked_at'         => now(),
        ]);

        return ['changed' => $changed, 'oldPrice' => $oldPrice, 'newPrice' => $newPrice];
    }
}
