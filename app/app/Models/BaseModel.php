<?php

namespace App\Models;

use App\Models\Concerns\UsesSchemaPrefix;
use Illuminate\Database\Eloquent\Model;

abstract class BaseModel extends Model
{
    use UsesSchemaPrefix;
}