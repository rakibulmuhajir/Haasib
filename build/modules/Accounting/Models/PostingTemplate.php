<?php

namespace App\Modules\Accounting\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PostingTemplate extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $connection = 'pgsql';
    protected $table = 'acct.posting_templates';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'company_id',
        'doc_type',
        'name',
        'description',
        'is_active',
        'is_default',
        'version',
        'effective_from',
        'effective_to',
        'created_by_user_id',
        'updated_by_user_id',
    ];

    protected $casts = [
        'company_id' => 'string',
        'is_active' => 'boolean',
        'is_default' => 'boolean',
        'version' => 'integer',
        'effective_from' => 'date',
        'effective_to' => 'date',
        'created_by_user_id' => 'string',
        'updated_by_user_id' => 'string',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    public function lines()
    {
        return $this->hasMany(PostingTemplateLine::class, 'template_id');
    }
}

