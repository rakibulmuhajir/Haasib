<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Create the tax schema
        DB::statement('CREATE SCHEMA IF NOT EXISTS tax');

        // Create jurisdictions table
        Schema::create('tax.jurisdictions', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('public.gen_random_uuid()'));
            $table->uuid('parent_id')->nullable();
            $table->char('country_code', 2);
            $table->string('code', 50);
            $table->string('name', 255);
            $table->string('level', 20)->default('country');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            // $table->foreign('parent_id')->references('id')->on('tax.jurisdictions')->nullOnDelete()->cascadeOnUpdate();
            // $table->foreign('country_code')->references('code')->on('public.countries')->cascadeOnUpdate();

            $table->unique(['country_code', 'code']);
            $table->index('country_code');
            $table->index('parent_id');
            $table->index('level');
        });

        // Seed with common jurisdictions, focusing on Saudi Arabia and key regions
        DB::table('tax.jurisdictions')->insert([
            // Saudi Arabia and regions
            [
                'id' => DB::raw('public.gen_random_uuid()'),
                'country_code' => 'SA',
                'code' => 'SA',
                'name' => 'Saudi Arabia',
                'level' => 'country',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => DB::raw('public.gen_random_uuid()'),
                'country_code' => 'SA',
                'code' => 'SA-Riyadh',
                'name' => 'Riyadh Region',
                'level' => 'state',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => DB::raw('public.gen_random_uuid()'),
                'country_code' => 'SA',
                'code' => 'SA-Mecca',
                'name' => 'Mecca Region',
                'level' => 'state',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => DB::raw('public.gen_random_uuid()'),
                'country_code' => 'SA',
                'code' => 'SA-Eastern',
                'name' => 'Eastern Region',
                'level' => 'state',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],

            // UAE
            [
                'id' => DB::raw('public.gen_random_uuid()'),
                'country_code' => 'AE',
                'code' => 'AE',
                'name' => 'United Arab Emirates',
                'level' => 'country',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],

            // Pakistan (as per original seed suggestion)
            [
                'id' => DB::raw('public.gen_random_uuid()'),
                'country_code' => 'PK',
                'code' => 'PK',
                'name' => 'Pakistan',
                'level' => 'country',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],

            // United States
            [
                'id' => DB::raw('public.gen_random_uuid()'),
                'country_code' => 'US',
                'code' => 'US',
                'name' => 'United States',
                'level' => 'country',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => DB::raw('public.gen_random_uuid()'),
                'country_code' => 'US',
                'code' => 'US-CA',
                'name' => 'California',
                'level' => 'state',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => DB::raw('public.gen_random_uuid()'),
                'country_code' => 'US',
                'code' => 'US-NY',
                'name' => 'New York',
                'level' => 'state',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],

            // European Union
            [
                'id' => DB::raw('public.gen_random_uuid()'),
                'country_code' => 'GB',
                'code' => 'GB',
                'name' => 'United Kingdom',
                'level' => 'country',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => DB::raw('public.gen_random_uuid()'),
                'country_code' => 'DE',
                'code' => 'DE',
                'name' => 'Germany',
                'level' => 'country',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => DB::raw('public.gen_random_uuid()'),
                'country_code' => 'FR',
                'code' => 'FR',
                'name' => 'France',
                'level' => 'country',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        // Enable RLS for jurisdictions (read-only for tenants)
        DB::statement('ALTER TABLE tax.jurisdictions ENABLE ROW LEVEL SECURITY');

        // Allow all authenticated users to read jurisdictions (read-only reference data)
        DB::statement("CREATE POLICY jurisdictions_read_all ON tax.jurisdictions
            FOR SELECT USING (true)");

        // Prevent direct writes to jurisdictions (only system/privileged users can modify)
        DB::statement("CREATE POLICY jurisdictions_deny_writes ON tax.jurisdictions
            FOR INSERT WITH CHECK (false)");
        DB::statement("CREATE POLICY jurisdictions_deny_updates ON tax.jurisdictions
            FOR UPDATE USING (false)");
        DB::statement("CREATE POLICY jurisdictions_deny_deletes ON tax.jurisdictions
            FOR DELETE USING (false)");
    }

    public function down(): void
    {
        Schema::dropIfExists('tax.jurisdictions');
        DB::statement('DROP SCHEMA IF EXISTS tax CASCADE');
    }
};