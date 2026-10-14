<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ActivityLogger
{
    public function record(
        string $action,
        string $description,
        ?Model $subject = null,
        ?User $actor = null,
        ?int $societyId = null,
        array $changedFields = []
    ): void {
        $request = app()->bound('request') ? request() : null;
        $actor ??= Auth::user();
        $loggedSocietyId = $societyId ?? $subject?->society_id;
        if ($loggedSocietyId === null && $actor?->role !== 'super_admin') {
            $loggedSocietyId = $actor?->society_id;
        }

        ActivityLog::create([
            'society_id' => $loggedSocietyId,
            'actor_user_id' => $actor?->id,
            'action' => $action,
            'subject_type' => $subject ? class_basename($subject) : null,
            'subject_id' => $subject?->getKey(),
            'description' => $description,
            'changed_fields' => $changedFields ?: null,
            'ip_address' => $request instanceof Request ? $request->ip() : null,
            'user_agent' => $request instanceof Request ? substr((string) $request->userAgent(), 0, 1000) : null,
            'created_at' => now(),
        ]);
    }
}
