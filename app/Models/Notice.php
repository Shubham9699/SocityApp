<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\Concerns\BelongsToSociety;

class Notice extends Model
{
    use BelongsToSociety;

    protected $fillable = [
        'title',
        'category',
        'priority',
        'content',
        'scheduled_publish_at',
        'expires_at',
        'is_published',
        'pinned',
        'created_by_user_id',
        'society_id',
    ];

    protected $casts = [
        'scheduled_publish_at' => 'datetime',
        'expires_at' => 'datetime',
        'is_published' => 'boolean',
        'pinned' => 'boolean',
    ];

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id')->withoutGlobalScope('society');
    }
}
