<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\Concerns\BelongsToSociety;

class Bill extends Model
{
    use BelongsToSociety;

    protected $fillable = [
        'bill_number',
        'flat_id',
        'bill_period',
        'base_maintenance',
        'utility_charges',
        'sinking_fund',
        'penalty_fine',
        'total_amount',
        'due_date',
        'payment_status',
        'paid_date',
        'payment_method',
        'payment_transaction_id',
        'payment_proof_path',
        'payment_submitted_at',
        'payment_review_note',
        'generated_by_user_id',
        'notes',
        'society_id',
    ];

    protected $casts = [
        'bill_period' => 'date',
        'due_date' => 'date',
        'paid_date' => 'datetime',
        'payment_submitted_at' => 'datetime',
        'base_maintenance' => 'decimal:2',
        'utility_charges' => 'decimal:2',
        'sinking_fund' => 'decimal:2',
        'penalty_fine' => 'decimal:2',
        'total_amount' => 'decimal:2',
    ];

    public function flat(): BelongsTo
    {
        return $this->belongsTo(Flat::class);
    }

    public function generatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generated_by_user_id')->withoutGlobalScope('society');
    }
}
