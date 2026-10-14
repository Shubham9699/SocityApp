<?php

namespace App\Http\Controllers;

use App\Models\Society;
use App\Services\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;

class SuperAdminController extends Controller
{
    public function switchSociety(Request $request, Society $society, ActivityLogger $activityLogger): RedirectResponse
    {
        abort_unless($society->status === 'active', 403, 'Only active society workspaces can be opened.');

        $request->session()->put('active_society_id', $society->id);
        $activityLogger->record(
            'workspace_opened',
            'Super admin opened the society workspace: '.$society->name,
            $society,
            $request->user(),
            $society->id
        );

        return redirect()->route('dashboard');
    }

    public function leaveSociety(Request $request, ActivityLogger $activityLogger): RedirectResponse
    {
        $societyId = $request->session()->pull('active_society_id');
        $society = $societyId ? Society::find($societyId) : null;
        $activityLogger->record(
            'workspace_closed',
            'Super admin left the society workspace: '.($society?->name ?? 'unknown society'),
            $society,
            $request->user(),
            $society?->id
        );

        return redirect()->route('dashboard', ['tab' => 'societies']);
    }

    public function updateSocietyStatus(Request $request, Society $society, ActivityLogger $activityLogger): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in(['active', 'suspended'])],
        ]);

        $previousStatus = $society->status;
        DB::transaction(function () use ($society, $validated, $request) {
            $society->update([
                'status' => $validated['status'],
                'approved_by_user_id' => $validated['status'] === 'active' ? $request->user()->id : $society->approved_by_user_id,
                'approved_at' => $validated['status'] === 'active' ? now() : $society->approved_at,
            ]);
            if ($validated['status'] === 'active') {
                foreach ($society->users()->where('role', 'admin')->where('status', 'pending')->get() as $admin) {
                    $admin->update(['status' => 'active']);
                }
            }
        });
        if ($validated['status'] === 'suspended' && (int) $request->session()->get('active_society_id') === $society->id) {
            $request->session()->forget('active_society_id');
        }

        $activityLogger->record(
            'status_changed',
            "Society {$society->name} status changed from {$previousStatus} to {$society->status}.",
            $society,
            $request->user(),
            $society->id,
            ['status']
        );

        return redirect()->route('dashboard', ['tab' => 'societies'])
            ->with('success', "Society {$society->name} is now {$society->status}.");
    }
}
