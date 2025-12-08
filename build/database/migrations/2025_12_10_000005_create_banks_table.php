<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bank.banks', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('public.gen_random_uuid()'));
            $table->string('name', 255);
            $table->string('swift_code', 11)->nullable();
            $table->char('country_code', 2)->nullable();
            $table->string('logo_url', 500)->nullable();
            $table->string('website', 255)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            // $table->foreign('country_code')->references('code')->on('public.countries')->nullOnDelete()->cascadeOnUpdate();
            $table->index('swift_code');
            $table->index('country_code');
        });

        // Seed with common banks
        DB::table('bank.banks')->insert([
            [
                'name' => 'National Commercial Bank',
                'swift_code' => 'NCBKSAJE',
                'country_code' => 'SA',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Riyad Bank',
                'swift_code' => 'RIBLSARI',
                'country_code' => 'SA',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Saudi British Bank (SABB)',
                'swift_code' => 'SABBSARI',
                'country_code' => 'SA',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Alinma Bank',
                'swift_code' => 'ALMASARI',
                'country_code' => 'SA',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('bank.banks');
    }
};