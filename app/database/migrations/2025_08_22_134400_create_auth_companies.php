<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

return new class extends Migration {
    public function up(): void
    {
        DB::statement('CREATE SCHEMA IF NOT EXISTS auth');

        // Skip creation if the table already exists in the auth schema
        $exists = DB::selectOne("select to_regclass('auth.companies') as reg")?->reg;
        if (! $exists) {
            Schema::create('auth.companies', function (Blueprint $t) {
                $t->uuid('id')->primary();
                $t->string('name');
                $t->string('slug')->unique();
                $t->string('base_currency', 3)->default('AED');
                $t->string('language', 5)->default('en');
                $t->string('locale', 10)->default('en_AE');
                $t->jsonb('settings')->nullable();
                $t->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('auth.companies');
    }
};
