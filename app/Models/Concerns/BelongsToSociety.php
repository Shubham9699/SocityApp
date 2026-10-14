<?php

namespace App\Models\Concerns;

use App\Models\Bill;
use App\Models\Complaint;
use App\Models\ComplaintLog;
use App\Models\Flat;
use App\Models\Society;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

trait BelongsToSociety
{
    protected static function bootBelongsToSociety(): void
    {
        static::addGlobalScope('society', function (Builder $query) {
            $request = app()->bound('request') ? request() : null;
            $societyId = $request && $request->hasSession()
                ? ($request->session()->get('active_society_id') ?? $request->session()->get('current_society_id'))
                : null;

            if ($societyId !== null) {
                $query->where($query->getModel()->qualifyColumn('society_id'), $societyId);
            }
        });

        static::creating(function ($model) {
            $request = app()->bound('request') ? request() : null;
            if ($model->society_id === null && $request && $request->hasSession()) {
                $model->society_id = $request->session()->get('active_society_id')
                    ?? $request->session()->get('current_society_id');
            }

            if ($model->society_id !== null) {
                return;
            }

            $model->society_id = match (true) {
                $model instanceof Flat => User::withoutGlobalScopes()->find($model->owner_user_id)?->society_id,
                $model instanceof Bill, $model instanceof Complaint, $model instanceof Tenant =>
                    Flat::withoutGlobalScopes()->find($model->flat_id)?->society_id,
                $model instanceof ComplaintLog =>
                    Complaint::withoutGlobalScopes()->find($model->complaint_id)?->society_id,
                $model instanceof Society => null,
                default => null,
            };
        });
    }
}
