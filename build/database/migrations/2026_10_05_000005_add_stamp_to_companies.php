<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * A company stamp and optional signature, printed on the final documents the
 * company issues. Existing table, so existing RLS applies. stamp_documents is
 * the set of document types it appears on (null = the defaults, see
 * Company::STAMP_DEFAULTS).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('auth.companies', function (Blueprint $table) {
            $table->string('stamp_path', 500)->nullable();
            $table->string('signature_path', 500)->nullable();
            $table->string('signer_name', 120)->nullable();
            $table->string('signer_title', 120)->nullable();
            $table->jsonb('stamp_documents')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('auth.companies', function (Blueprint $table) {
            $table->dropColumn(['stamp_path', 'signature_path', 'signer_name', 'signer_title', 'stamp_documents']);
        });
    }
};
