<?php

namespace App\Jobs;

use App\Mail\PriceChangedMail;
use App\Models\TrackedProduct;
use App\Services\PriceCheckService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class CheckProductPricesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 600; // 10 minutes total, since each scrape can take up to 30s

    public function handle(PriceCheckService $service): void
    {
        TrackedProduct::with('user')
            ->chunk(20, function ($products) use ($service) {
                foreach ($products as $product) {
                    $result = $service->check($product);

                    Log::info('Product checked', [
                        'id' => $product->id,
                        'changed' => $result['changed'],
                        'oldPrice' => $result['oldPrice'],
                        'newPrice' => $result['newPrice'],
                    ]);

                    if ($result['changed']) {
                        Mail::to($product->user->email)->send(
                            new PriceChangedMail(
                                $product,
                                $result['oldPrice'],
                                $result['newPrice'],
                            )
                        );
                    }
                }
            });
    }
}
