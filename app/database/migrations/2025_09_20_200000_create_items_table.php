<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('acct.items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('company_id');
            $table->string('item_code', 64)->nullable();
            $table->string('name', 255);
            $table->text('description')->nullable();
            $table->decimal('unit_price', 15, 2)->default(0);
            $table->uuid('currency_id')->nullable();
            $table->string('item_type', 32)->default('service'); // service|product
            $table->uuid('category_id')->nullable(); // optional; category table not enforced here
            $table->boolean('taxable')->default(true);
            $table->boolean('track_inventory')->default(false);
            $table->decimal('reorder_level', 15, 4)->default(0);
            $table->decimal('stock_quantity', 15, 4)->default(0);
            $table->boolean('is_active')->default(true);
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['company_id', 'item_code']);
        });

        Schema::table('acct.items', function (Blueprint $table) {
            $table->foreign('company_id')->references('id')->on('auth.companies')->onDelete('cascade');
            $table->foreign('currency_id')->references('id')->on('public.currencies')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('acct.items');
    }
};
