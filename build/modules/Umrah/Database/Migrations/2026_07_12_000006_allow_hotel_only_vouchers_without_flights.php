<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('umrah.vouchers', function (Blueprint $table) {
            $table->string('onward_airline', 150)->nullable()->change();
            $table->timestamp('onward_departure_at')->nullable()->change();
            $table->timestamp('onward_arrival_at')->nullable()->change();
            $table->string('return_airline', 150)->nullable()->change();
            $table->timestamp('return_departure_at')->nullable()->change();
            $table->timestamp('return_arrival_at')->nullable()->change();
        });
    }

    public function down(): void
    {
        DB::statement("UPDATE umrah.vouchers SET
            onward_airline = COALESCE(onward_airline, 'N/A'),
            onward_departure_at = COALESCE(onward_departure_at, created_at),
            onward_arrival_at = COALESCE(onward_arrival_at, created_at + interval '1 minute'),
            return_airline = COALESCE(return_airline, 'N/A'),
            return_departure_at = COALESCE(return_departure_at, created_at + interval '2 minutes'),
            return_arrival_at = COALESCE(return_arrival_at, created_at + interval '3 minutes')");

        Schema::table('umrah.vouchers', function (Blueprint $table) {
            $table->string('onward_airline', 150)->nullable(false)->change();
            $table->timestamp('onward_departure_at')->nullable(false)->change();
            $table->timestamp('onward_arrival_at')->nullable(false)->change();
            $table->string('return_airline', 150)->nullable(false)->change();
            $table->timestamp('return_departure_at')->nullable(false)->change();
            $table->timestamp('return_arrival_at')->nullable(false)->change();
        });
    }
};
