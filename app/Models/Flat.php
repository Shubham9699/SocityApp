<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use App\Models\Concerns\BelongsToSociety;

class Flat extends Model
{
    use BelongsToSociety;

    protected $fillable = [
        'wing',
        'flat_number',
        'floor',
        'carpet_sqft',
        'owner_user_id',
        'occupancy_status',
        'parking_slot',
        'monthly_maintenance_amount',
        'society_id',
    ];

    protected $casts = [
        'monthly_maintenance_amount' => 'decimal:2',
    ];

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function society(): BelongsTo
    {
        return $this->belongsTo(Society::class);
    }

    public function bills(): HasMany
    {
        return $this->hasMany(Bill::class);
    }

    public function complaints(): HasMany
    {
        return $this->hasMany(Complaint::class);
    }

    public function tenant(): HasOne
    {
        return $this->hasOne(Tenant::class);
    }
}
