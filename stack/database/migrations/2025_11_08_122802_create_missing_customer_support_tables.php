<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('acct.customer_contacts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('customer_id');
            $table->string('first_name');
            $table->string('last_name');
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('position')->nullable();
            $table->boolean('is_primary')->default(false);
            $table->timestamps();
            $table->softDeletes();
            
            $table->foreign('customer_id')->references('id')->on('acct.customers')->onDelete('cascade');
            $table->index(['customer_id', 'is_primary']);
        });

        Schema::create('acct.customer_addresses', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('customer_id');
            $table->string('address_line_1');
            $table->string('address_line_2')->nullable();
            $table->string('city');
            $table->string('state')->nullable();
            $table->string('postal_code')->nullable();
            $table->string('country');
            $table->string('address_type')->default('billing'); // billing, shipping, etc.
            $table->boolean('is_default')->default(false);
            $table->timestamps();
            $table->softDeletes();
            
            $table->foreign('customer_id')->references('id')->on('acct.customers')->onDelete('cascade');
            $table->index(['customer_id', 'address_type']);
        });

        Schema::create('acct.customer_credit_limits', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('customer_id');
            $table->decimal('limit_amount', 15, 2);
            $table->date('effective_at');
            $table->string('status')->default('pending'); // pending, approved, rejected
            $table->text('notes')->nullable();
            $table->uuid('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
            
            $table->foreign('customer_id')->references('id')->on('acct.customers')->onDelete('cascade');
            $table->index(['customer_id', 'status', 'effective_at']);
        });

        Schema::create('acct.customer_communications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('customer_id');
            $table->string('type'); // email, phone, meeting, note
            $table->string('subject')->nullable();
            $table->text('content');
            $table->string('direction'); // inbound, outbound
            $table->uuid('created_by_user_id');
            $table->timestamps();
            $table->softDeletes();
            
            $table->foreign('customer_id')->references('id')->on('acct.customers')->onDelete('cascade');
            $table->index(['customer_id', 'type', 'created_at']);
        });

        Schema::create('acct.customer_groups', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('company_id');
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('color', 7)->nullable(); // hex color code
            $table->timestamps();
            $table->softDeletes();
            
            $table->foreign('company_id')->references('id')->on('auth.companies')->onDelete('cascade');
            $table->unique(['company_id', 'name']);
        });

        Schema::create('acct.customer_group_members', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('customer_id');
            $table->uuid('group_id');
            $table->timestamps();
            
            $table->foreign('customer_id')->references('id')->on('acct.customers')->onDelete('cascade');
            $table->foreign('group_id')->references('id')->on('acct.customer_groups')->onDelete('cascade');
            $table->unique(['customer_id', 'group_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('acct.customer_group_members');
        Schema::dropIfExists('acct.customer_groups');
        Schema::dropIfExists('acct.customer_communications');
        Schema::dropIfExists('acct.customer_credit_limits');
        Schema::dropIfExists('acct.customer_addresses');
        Schema::dropIfExists('acct.customer_contacts');
    }
};
