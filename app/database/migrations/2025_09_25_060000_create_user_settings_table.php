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
        Schema::create('auth.user_settings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->string('group')->default('general'); // general, currency, notifications, etc.
            $table->string('key');
            $table->json('value')->nullable();
            $table->timestamps();

            // Foreign key
            $table->foreign('user_id')->references('id')->on('auth.users')->onDelete('cascade');

            // Unique constraint - user can only have one setting per group/key
            $table->unique(['user_id', 'group', 'key'], 'unique_user_setting');

            // Indexes
            $table->index(['user_id', 'group']);
            $table->index(['group', 'key']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('auth.user_settings');
    }
};
