<?php

namespace App\Jobs;

use App\Services\CurrencyService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SyncExchangeRates implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public string $provider = 'ecb'
    ) {}

    public function handle(CurrencyService $currencyService): void
    {
        try {
            $result = $currencyService->syncExchangeRatesFromAPI($this->provider);
            Log::info('FX sync completed', ['provider' => $this->provider, 'summary' => $result]);
        } catch (\Throwable $e) {
            Log::error('FX sync job failed', ['provider' => $this->provider, 'error' => $e->getMessage()]);
            throw $e;
        }
    }
}
