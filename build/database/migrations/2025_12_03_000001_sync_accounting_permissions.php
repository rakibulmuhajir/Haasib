<?php

use App\Constants\Permissions;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        $names = Permissions::all();

        $existing = DB::table('permissions')
            ->whereIn('name', $names)
            ->pluck('name')
            ->all();

        $missing = array_diff($names, $existing);

        if (empty($missing)) {
            return;
        }

        $now = now();
        $rows = array_map(function (string $name) use ($now) {
            return [
                'id' => (string) Str::orderedUuid(),
                'name' => $name,
                'guard_name' => 'web',
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }, $missing);

        DB::table('permissions')->insert($rows);
    }

    public function down(): void
    {
        // Only remove the permissions we add here
        DB::table('permissions')
            ->whereIn('name', Permissions::all())
            ->where('guard_name', 'web')
            ->delete();
    }
};
