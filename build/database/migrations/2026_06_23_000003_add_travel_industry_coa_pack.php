<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        DB::table('acct.industry_coa_packs')->updateOrInsert(
            ['code' => 'travel'],
            [
                'name' => 'Travel Agency',
                'description' => 'Travel and Umrah agency operations. The first workflow enabled is Umrah visa groups.',
                'is_active' => true,
                'sort_order' => 17,
                'created_at' => $now,
                'updated_at' => $now,
            ]
        );

        $travelId = DB::table('acct.industry_coa_packs')->where('code', 'travel')->value('id');
        $umrahId = DB::table('acct.industry_coa_packs')->where('code', 'umrah')->value('id');

        if (! $travelId || ! $umrahId) {
            return;
        }

        $templates = DB::table('acct.industry_coa_templates')
            ->where('industry_pack_id', $umrahId)
            ->orderBy('sort_order')
            ->get();

        foreach ($templates as $template) {
            DB::table('acct.industry_coa_templates')->updateOrInsert(
                [
                    'industry_pack_id' => $travelId,
                    'code' => $template->code,
                ],
                [
                    'name' => $template->name,
                    'type' => $template->type,
                    'subtype' => $template->subtype,
                    'normal_balance' => $template->normal_balance,
                    'is_contra' => $template->is_contra,
                    'is_system' => $template->is_system,
                    'system_identifier' => $template->system_identifier,
                    'description' => $template->description,
                    'sort_order' => $template->sort_order,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );
        }
    }

    public function down(): void
    {
        $travelId = DB::table('acct.industry_coa_packs')->where('code', 'travel')->value('id');

        if ($travelId) {
            DB::table('acct.industry_coa_templates')->where('industry_pack_id', $travelId)->delete();
            DB::table('acct.industry_coa_packs')->where('id', $travelId)->delete();
        }
    }
};
