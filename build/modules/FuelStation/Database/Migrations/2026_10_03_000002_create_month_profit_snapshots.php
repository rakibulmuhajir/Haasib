<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fuel.month_profit_snapshots', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('company_id')->references('id')->on('auth.companies')->cascadeOnDelete();
            $table->date('month');
            $table->string('method', 30);
            $table->jsonb('payload');
            $table->foreignUuid('finalized_by_user_id')->nullable()->references('id')->on('auth.users')->nullOnDelete();
            $table->timestamp('finalized_at');
            $table->timestamp('reopened_at')->nullable();
            $table->index(['company_id', 'month']);
        });
        DB::statement("ALTER TABLE fuel.month_profit_snapshots ADD CONSTRAINT month_profit_snapshots_method_check CHECK (method IN ('inventory_cost', 'next_month_purchase_rate'))");
        DB::statement('ALTER TABLE fuel.month_profit_snapshots ADD CONSTRAINT month_profit_snapshots_first_day_check CHECK (EXTRACT(DAY FROM month) = 1)');
        DB::statement('CREATE UNIQUE INDEX month_profit_snapshots_active_month ON fuel.month_profit_snapshots (company_id, month) WHERE reopened_at IS NULL');
        DB::statement('ALTER TABLE fuel.month_profit_snapshots ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE fuel.month_profit_snapshots FORCE ROW LEVEL SECURITY');
        DB::statement("CREATE POLICY month_profit_snapshots_company_isolation ON fuel.month_profit_snapshots FOR ALL USING (company_id = NULLIF(current_setting('app.current_company_id', true), '')::uuid) WITH CHECK (company_id = NULLIF(current_setting('app.current_company_id', true), '')::uuid)");
    }

    public function down(): void
    {
        Schema::dropIfExists('fuel.month_profit_snapshots');
    }
};
