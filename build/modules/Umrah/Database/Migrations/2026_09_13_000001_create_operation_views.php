<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('umrah.operation_views', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('company_id')->constrained('auth.companies')->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained('auth.users')->cascadeOnDelete();
            $table->string('name', 80);
            $table->jsonb('filters');
            $table->timestamps();
            $table->unique(['company_id', 'user_id', 'name']);
        });
        DB::statement('ALTER TABLE umrah.operation_views ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE umrah.operation_views FORCE ROW LEVEL SECURITY');
        DB::statement("CREATE POLICY operation_views_company_isolation ON umrah.operation_views FOR ALL USING (company_id = current_setting('app.current_company_id', true)::uuid) WITH CHECK (company_id = current_setting('app.current_company_id', true)::uuid)");
    }

    public function down(): void
    {
        Schema::dropIfExists('umrah.operation_views');
    }
};
