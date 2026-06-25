<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('umrah.vouchers', function (Blueprint $table) {
            $table->string('onward_departure_city', 150)->nullable();
            $table->string('onward_arrival_city', 150)->nullable();
            $table->string('return_departure_city', 150)->nullable();
            $table->string('return_arrival_city', 150)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('umrah.vouchers', function (Blueprint $table) {
            $table->dropColumn([
                'onward_departure_city',
                'onward_arrival_city',
                'return_departure_city',
                'return_arrival_city',
            ]);
        });
    }
};
