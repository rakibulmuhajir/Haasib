<?php

namespace App\Modules\Accounting\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PostingTemplateLine extends Model
{
    use HasFactory, HasUuids;

    protected $connection = 'pgsql';
    protected $table = 'acct.posting_template_lines';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'template_id',
        'role',
        'account_id',
        'description',
        'precedence',
        'is_required',
    ];

    protected $casts = [
        'template_id' => 'string',
        'account_id' => 'string',
        'precedence' => 'integer',
        'is_required' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function template()
    {
        return $this->belongsTo(PostingTemplate::class, 'template_id');
    }

    public function account()
    {
        return $this->belongsTo(Account::class, 'account_id');
    }
}

