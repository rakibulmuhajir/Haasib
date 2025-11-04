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
        Schema::create('command_templates', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('command_id');
            $table->uuid('user_id');
            $table->uuid('company_id');
            $table->string('name');
            $table->json('parameter_values');
            $table->boolean('is_shared')->default(false);
            $table->timestamps();

            $table->foreign('command_id')->references('id')->on('commands')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('auth.users')->onDelete('cascade');
            $table->foreign('company_id')->references('id')->on('auth.companies')->onDelete('cascade');
            $table->index(['command_id', 'is_shared']);
            $table->index(['user_id', 'is_shared']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('command_templates');
    }
};
