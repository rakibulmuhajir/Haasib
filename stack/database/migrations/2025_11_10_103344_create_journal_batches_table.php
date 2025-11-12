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
        Schema::create('acct.journal_batches', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('company_id');
            $table->string('batch_number', 50)->unique();
            $table->string('description', 500);
            $table->date('batch_date');
            $table->string('status', 20)->default('draft'); // draft, posted, voided
            $table->decimal('total_debits', 15, 2)->default(0);
            $table->decimal('total_credits', 15, 2)->default(0);
            $table->decimal('total_entries', 8, 2)->default(0);
            $table->json('metadata')->nullable();
            $table->uuid('created_by_user_id')->nullable();
            $table->uuid('posted_by_user_id')->nullable();
            $table->timestamp('posted_at')->nullable();
            $table->timestamps();

            $table->foreign('company_id')->references('id')->on('auth.companies')->onDelete('cascade');
            $table->foreign('created_by_user_id')->references('id')->on('auth.users')->onDelete('set null');
            $table->foreign('posted_by_user_id')->references('id')->on('auth.users')->onDelete('set null');

            $table->index(['company_id', 'batch_date']);
            $table->index(['company_id', 'status']);
            $table->index('batch_number');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('acct.journal_batches');
    }
};
