<?php

namespace App\Providers;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;
use Modules\Accounting\Domain\Payments\Events\PaymentCreated;
use Modules\Accounting\Domain\Payments\Telemetry\PaymentMetrics;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Vite::prefetch(concurrency: 3);

        // Register payment telemetry event listeners
        $this->registerPaymentTelemetry();

        // Custom validation rule for customers table with schema
        Validator::extend('exists_customer', function ($attribute, $value, $parameters, $validator) {
            $exists = DB::table('hrm.customers')
                ->where('customer_id', $value)
                ->exists();

            return $exists;
        });

        // Custom validation rule for currencies table with schema
        Validator::extend('exists_currency', function ($attribute, $value, $parameters, $validator) {
            if (! $value) {
                return true;
            } // nullable field

            $exists = DB::table('public.currencies')
                ->where('id', $value)
                ->exists();

            return $exists;
        });
    }

    /**
     * Register payment telemetry event listeners.
     */
    private function registerPaymentTelemetry(): void
    {
        // Listen for payment creation events
        \Event::listen(PaymentCreated::class, function (PaymentCreated $event) {
            $paymentData = $event->paymentData;
            PaymentMetrics::paymentCreated(
                $paymentData['company_id'] ?? 'unknown',
                $paymentData['payment_method'] ?? 'unknown',
                $paymentData['amount'] ?? 0
            );
        });
    }
}
