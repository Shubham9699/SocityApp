<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\Concerns\BelongsToSociety;

class Complaint extends Model
{
    use BelongsToSociety;

    protected $fillable = [
        'ticket_number',
        'flat_id',
        'resident_user_id',
        'category',
        'subject',
        'description',
        'priority',
        'status',
        'assigned_to_user_id',
        'resolution_notes',
        'resolved_at',
        'closed_at',
        'society_id',
    ];

    protected $casts = [
        'resolved_at' => 'datetime',
        'closed_at' => 'datetime',
    ];

    public function flat(): BelongsTo
    {
        return $this->belongsTo(Flat::class);
    }

    public function resident(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resident_user_id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to_user_id');
    }

    public function logs(): HasMany
    {
        return $this->hasMany(ComplaintLog::class)->latest();
    }
}
