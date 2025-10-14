<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('command_overlays', function (Blueprint $t) {
            $t->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            // Scope: null = global; company_id for tenant; user_id for per-user
            $t->uuid('company_id')->nullable()->index();
            $t->uuid('user_id')->nullable()->index();
            // Target
            $t->string('entity_id');
            $t->string('verb_id')->nullable(); // null means entity-level overlay
            // Overrides
            $t->boolean('enabled')->nullable(); // null = no change
            $t->string('label_override')->nullable();
            $t->jsonb('aliases_override')->nullable();
            $t->integer('order_override')->nullable();
            $t->timestamps();

            $t->index(['entity_id','verb_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('command_overlays');
    }
};

