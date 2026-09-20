<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The paper behind a posted transaction — an electricity bill, a repair receipt, a
 * delivery note. An expense with no document is an assertion; an expense with the bill
 * attached is evidence, and evidence is the difference between books that survive a
 * question and books that do not.
 *
 * Hung off acct.transactions rather than off expenses specifically: an expense here IS a
 * transaction (Expense\CreateAction posts an ordinary journal), and the same need turns up
 * for bills and manual journals. One table rather than one per document type.
 *
 * Files live on the private disk, never the public one: a supplier invoice carries
 * account numbers and pricing and has no business being fetchable by URL guess.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('acct.transaction_attachments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('company_id')->references('id')->on('auth.companies');
            $table->uuid('transaction_id');
            $table->string('disk', 32)->default('local');
            $table->string('path', 512);
            $table->string('original_name', 255);
            $table->string('mime_type', 128)->nullable();
            $table->unsignedBigInteger('size_bytes')->nullable();
            $table->uuid('uploaded_by_user_id')->nullable();
            $table->timestamps();

            $table->foreign('transaction_id')->references('id')->on('acct.transactions')->cascadeOnDelete();
            $table->foreign('uploaded_by_user_id')->references('id')->on('auth.users')->nullOnDelete();
            $table->index(['company_id', 'transaction_id'], 'transaction_attachments_lookup');
        });

        DB::statement('ALTER TABLE acct.transaction_attachments ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE acct.transaction_attachments FORCE ROW LEVEL SECURITY');
        DB::statement("CREATE POLICY transaction_attachments_company ON acct.transaction_attachments USING (
            company_id = nullif(current_setting('app.current_company_id', true), '')::uuid
            OR current_setting('app.is_super_admin', true) = 'true')");
    }

    public function down(): void
    {
        Schema::dropIfExists('acct.transaction_attachments');
    }
};
