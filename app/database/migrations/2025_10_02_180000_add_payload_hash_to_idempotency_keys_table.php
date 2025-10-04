<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('public.idempotency_keys', function (Blueprint $table) {
            $table->string('payload_hash', 64)->nullable()->after('key');
            $table->index('payload_hash');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('public.idempotency_keys', function (Blueprint $table) {
            $table->dropIndex(['payload_hash']);
            $table->dropColumn('payload_hash');
        });
    }
};
