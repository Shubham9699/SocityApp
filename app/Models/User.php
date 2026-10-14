<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\Concerns\BelongsToSociety;

class User extends Authenticatable
{
    use HasFactory, Notifiable;
    use BelongsToSociety;

    protected $fillable = [
        'full_name',
        'email',
        'phone',
        'password',
        'role',
        'emergency_contact',
        'avatar_url',
        'status',
        'last_login_at',
        'society_id',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'last_login_at' => 'datetime',
        'password' => 'hashed',
    ];

    public function flat(): HasOne
    {
        return $this->hasOne(Flat::class, 'owner_user_id');
    }

    public function complaints(): HasMany
    {
        return $this->hasMany(Complaint::class, 'resident_user_id');
    }

    public function society(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Society::class);
    }
}
