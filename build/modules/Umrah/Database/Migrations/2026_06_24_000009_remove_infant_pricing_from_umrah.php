<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('umrah.visa_vendors', function (Blueprint $table) {
            $table->dropColumn(['infant_retail_amount', 'infant_cost_amount']);
        });

        Schema::table('umrah.visa_services', function (Blueprint $table) {
            $table->dropColumn(['infant_retail_amount', 'infant_cost_amount']);
        });
    }

    public function down(): void
    {
        Schema::table('umrah.visa_vendors', function (Blueprint $table) {
            $table->decimal('infant_retail_amount', 15, 2)->default(0)->after('child_cost_amount');
            $table->decimal('infant_cost_amount', 15, 2)->default(0)->after('infant_retail_amount');
        });

        Schema::table('umrah.visa_services', function (Blueprint $table) {
            $table->decimal('infant_retail_amount', 15, 2)->default(0)->after('child_cost_amount');
            $table->decimal('infant_cost_amount', 15, 2)->default(0)->after('infant_retail_amount');
        });
    }
};
