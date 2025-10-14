<?php

namespace App\Models;

use Spatie\Permission\Models as SpatieModels;

class Role extends SpatieModels\Role
{
    protected $table = 'public.roles';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $casts = [
        'id' => 'string',
    ];
}
