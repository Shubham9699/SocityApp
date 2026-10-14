<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\Concerns\BelongsToSociety;

class ComplaintLog extends Model
{
    use BelongsToSociety;

    public $timestamps = false;

    protected $fillable = [
        'complaint_id',
        'action_by_user_id',
        'previous_status',
        'new_status',
        'remarks',
        'created_at',
        'society_id',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'action_by_user_id')->withoutGlobalScope('society');
    }
}
