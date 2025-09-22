<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('languages', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            // ISO 639-1 two-letter where available; fall back to 639-2/3
            $table->string('code', 8)->unique();
            $table->string('name');
            $table->string('native_name')->nullable();
            $table->string('iso_639_1', 2)->nullable()->index();
            $table->string('iso_639_2', 3)->nullable()->index();
            $table->string('script', 10)->nullable(); // e.g., Latn, Cyrl
            $table->boolean('rtl')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('languages');
    }
};
