<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Company extends Model
{
    use HasUuids;

    protected $connection = 'pgsql';

    protected $table = 'companies';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $guarded = [];
}
