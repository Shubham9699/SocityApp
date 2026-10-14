<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\Concerns\BelongsToSociety;

class Tenant extends Model
{
    use BelongsToSociety;

    protected $fillable = [
        'flat_id',
        'full_name',
        'phone',
        'email',
        'agreement_path',
        'society_id',
    ];

    public function flat(): BelongsTo
    {
        return $this->belongsTo(Flat::class);
    }
}
