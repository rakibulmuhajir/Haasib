<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('umrah.agents', function (Blueprint $table) {
            $table->boolean('can_create_voucher')->default(true)->after('notes');
            $table->boolean('can_approve_voucher')->default(false)->after('can_create_voucher');
            $table->boolean('can_edit_voucher')->default(false)->after('can_approve_voucher');
            $table->integer('voucher_cutoff_hours')->default(6)->after('can_edit_voucher');
        });
        DB::statement('ALTER TABLE umrah.agents ADD CONSTRAINT agents_voucher_cutoff_check CHECK (voucher_cutoff_hours IN (2, 6, 12, 18, 24, 48))');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE umrah.agents DROP CONSTRAINT IF EXISTS agents_voucher_cutoff_check');
        Schema::table('umrah.agents', function (Blueprint $table) {
            $table->dropColumn(['can_create_voucher', 'can_approve_voucher', 'can_edit_voucher', 'voucher_cutoff_hours']);
        });
    }
};
