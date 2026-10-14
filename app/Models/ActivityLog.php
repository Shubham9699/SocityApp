<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActivityLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'society_id',
        'actor_user_id',
        'action',
        'subject_type',
        'subject_id',
        'description',
        'changed_fields',
        'ip_address',
        'user_agent',
        'created_at',
    ];

    protected $casts = [
        'changed_fields' => 'array',
        'created_at' => 'datetime',
    ];

    public function society(): BelongsTo
    {
        return $this->belongsTo(Society::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id')->withoutGlobalScope('society');
    }
}
