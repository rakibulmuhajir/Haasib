<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * The date a company stamp starts applying: only documents dated on or after
 * it are stamped. Null = no restriction. Existing table, existing RLS.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('auth.companies', function (Blueprint $table) {
            $table->date('stamp_from')->nullable();
        });
        // A company that already has a stamp stamps only what it issues from now on (the owner's
        // choice, 2026-10-05); the date is editable in Company settings.
        \Illuminate\Support\Facades\DB::table('auth.companies')
            ->where(fn ($q) => $q->whereNotNull('stamp_path')->orWhereNotNull('signature_path'))
            ->update(['stamp_from' => now()->toDateString()]);
    }

    public function down(): void
    {
        Schema::table('auth.companies', function (Blueprint $table) {
            $table->dropColumn('stamp_from');
        });
    }
};
