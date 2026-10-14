<?php

namespace App\Observers;

use App\Models\ActivityLog;
use App\Models\Bill;
use App\Models\Complaint;
use App\Models\ComplaintLog;
use App\Models\Flat;
use App\Models\Notice;
use App\Models\Society;
use App\Models\Tenant;
use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Database\Eloquent\Model;

class ActivityObserver
{
    private const SENSITIVE_FIELDS = [
        'password',
        'remember_token',
        'payment_proof_path',
        'agreement_path',
        'ip_address',
        'user_agent',
    ];

    public function created(Model $model): void
    {
        $this->log($model, 'created', $this->description($model, 'created'));
    }

    public function updated(Model $model): void
    {
        $fields = array_values(array_diff(array_keys($model->getChanges()), self::SENSITIVE_FIELDS));
        $this->log($model, 'updated', $this->description($model, 'updated'), $fields);
    }

    public function deleted(Model $model): void
    {
        $this->log($model, 'deleted', $this->description($model, 'deleted'));
    }

    private function log(Model $model, string $action, string $description, array $fields = []): void
    {
        if ($model instanceof ActivityLog) {
            return;
        }

        app(ActivityLogger::class)->record($action, $description, $model, changedFields: $fields);
    }

    private function description(Model $model, string $action): string
    {
        $label = match (true) {
            $model instanceof Society => $model->name,
            $model instanceof User => $model->full_name,
            $model instanceof Flat => $model->wing.'-'.$model->flat_number,
            $model instanceof Bill => $model->bill_number,
            $model instanceof Complaint => $model->ticket_number,
            $model instanceof ComplaintLog => 'Complaint status history',
            $model instanceof Notice => $model->title,
            $model instanceof Tenant => $model->full_name,
            default => class_basename($model),
        };

        return class_basename($model).' '.$action.': '.$label;
    }
}
