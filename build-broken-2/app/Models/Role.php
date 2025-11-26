<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Spatie\Permission\Models as SpatieModels;

class Role extends SpatieModels\Role
{
    use HasUuids;

    protected $table = 'public.roles';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $casts = [
        'id' => 'string',
    ];
}
