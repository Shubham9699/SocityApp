<?php

namespace App\Http\Controllers;

use App\Models\Society;
use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SocietyRegistrationController extends Controller
{
    public function create(): View
    {
        return view('auth.register-society');
    }

    public function store(Request $request, ActivityLogger $activityLogger): RedirectResponse
    {
        $validated = $request->validate([
            'society_name' => ['required', 'string', 'max:150'],
            'address' => ['nullable', 'string', 'max:1000'],
            'admin_name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:150', Rule::unique('users', 'email')],
            'phone' => ['required', 'string', 'max:20'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        [$society, $admin] = DB::transaction(function () use ($validated) {
            $slug = Str::slug($validated['society_name']) ?: 'society';
            $baseSlug = $slug;
            $suffix = 2;
            while (Society::where('slug', $slug)->exists()) {
                $slug = $baseSlug.'-'.$suffix++;
            }

            $society = Society::create([
                'name' => $validated['society_name'],
                'slug' => $slug,
                'address' => $validated['address'] ?? null,
                'status' => 'pending',
            ]);

            $admin = User::create([
                'full_name' => $validated['admin_name'],
                'email' => strtolower($validated['email']),
                'phone' => $validated['phone'],
                'password' => $validated['password'],
                'role' => 'admin',
                'status' => 'pending',
                'society_id' => $society->id,
            ]);

            return [$society, $admin];
        });

        $activityLogger->record(
            'registered',
            'Society registration submitted: '.$society->name,
            $society,
            actor: $admin,
            societyId: $society->id
        );

        return redirect()->route('login')->with(
            'success',
            'Society registration submitted. You can sign in after the super admin approves your society.'
        );
    }
}
