<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('countries', function (Blueprint $table) {
            $table->id();
            // ISO 3166-1 alpha-2 and alpha-3
            $table->char('code', 2)->unique();
            $table->char('alpha3', 3)->nullable()->index();
            $table->string('name');
            $table->string('native_name')->nullable();
            $table->string('region')->nullable();
            $table->string('subregion')->nullable();
            $table->string('emoji', 8)->nullable();
            $table->string('capital')->nullable();
            $table->string('calling_code', 8)->nullable();
            $table->boolean('eea_member')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('countries');
    }
};

