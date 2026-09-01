<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Why a refund happened, in a form something can count.
 *
 * The sentence stays and is still what a person reads. This sits beside
 * it so the same question can be asked of a thousand refunds at once --
 * how much went back because a passenger did not travel, how much because
 * a supplier had overcharged us.
 *
 * Nullable, because every refund written before today has a sentence and
 * no category, and guessing one from the words would invent a fact.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('umrah.refunds', function (Blueprint $table) {
            $table->string('reason_category', 40)->nullable()->after('reason');
        });
    }

    public function down(): void
    {
        Schema::table('umrah.refunds', function (Blueprint $table) {
            $table->dropColumn('reason_category');
        });
    }
};
