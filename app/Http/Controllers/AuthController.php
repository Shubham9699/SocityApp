<?php

namespace App\Http\Controllers;

use App\Models\Flat;
use App\Models\Society;
use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AuthController extends Controller
{
    public function showLogin(): View
    {
        return view('auth.login');
    }

    public function showRegister(): View
    {
        return view('auth.register', [
            'societies' => Society::where('status', 'active')->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function showForgotPassword(): View
    {
        return view('auth.forgot-password');
    }

    public function showResetPassword(string $token): View
    {
        return view('auth.reset-password', ['token' => $token]);
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'role' => ['required', Rule::in(['admin', 'resident', 'staff', 'super_admin'])],
        ]);

        $remember = $request->boolean('remember');
        $role = $credentials['role'];
        unset($credentials['role']);
        $credentials['status'] = 'active';

        if (! Auth::attempt($credentials, $remember)) {
            app(ActivityLogger::class)->record('login_failed', 'Unsuccessful sign-in attempt.');

            return back()->withErrors([
                'email' => 'The email, password, or selected account type is incorrect.',
            ])->onlyInput('email', 'role');
        }

        if (Auth::user()->role !== $role) {
            app(ActivityLogger::class)->record('login_failed', 'Sign-in attempt used the wrong account type.', actor: Auth::user());
            Auth::logout();

            return back()->withErrors([
                'email' => 'This account does not have the selected role.',
            ])->onlyInput('email', 'role');
        }

        if (Auth::user()->role !== 'super_admin' && Auth::user()->society?->status !== 'active') {
            app(ActivityLogger::class)->record('login_blocked', 'Sign-in blocked because the society workspace is inactive.', actor: Auth::user());
            Auth::logout();

            return back()->withErrors([
                'email' => 'This society is not currently active. Contact the super admin for help.',
            ])->onlyInput('email', 'role');
        }

        $request->session()->regenerate();
        if (Auth::user()->role === 'super_admin') {
            $request->session()->forget(['current_society_id', 'active_society_id']);
        } else {
            $request->session()->put('current_society_id', Auth::user()->getRawOriginal('society_id'));
        }
        Auth::user()->forceFill(['last_login_at' => now()])->save();
        app(ActivityLogger::class)->record('login', 'User signed in.', actor: Auth::user());

        return redirect()->intended(route('dashboard'));
    }

    public function register(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'full_name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'string', 'email', 'max:150', 'unique:users,email'],
            'phone' => ['required', 'string', 'max:20'],
            'society_id' => ['required', Rule::exists('societies', 'id')->where(fn ($query) => $query->where('status', 'active'))],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'wing' => ['required', 'string', 'max:10', 'alpha_num'],
            'flat_number' => [
                'required',
                'string',
                'max:20',
                Rule::unique('flats')->where(fn ($query) => $query
                    ->where('society_id', $request->input('society_id'))
                    ->where('wing', strtoupper($request->input('wing')))),
            ],
            'occupancy_status' => ['required', Rule::in(['owner_occupied', 'tenant_occupied'])],
        ]);

        $user = DB::transaction(function () use ($validated) {
            $user = User::create([
                'full_name' => $validated['full_name'],
                'email' => strtolower($validated['email']),
                'phone' => $validated['phone'],
                'password' => $validated['password'],
                'role' => 'resident',
                'status' => 'active',
                'society_id' => $validated['society_id'],
            ]);

            $wing = strtoupper($validated['wing']);
            $flatNumber = $validated['flat_number'];

            Flat::create([
                'wing' => $wing,
                'flat_number' => $flatNumber,
                'floor' => max(1, (int) substr($flatNumber, 0, 1)),
                'carpet_sqft' => 1050,
                'owner_user_id' => $user->id,
                'occupancy_status' => $validated['occupancy_status'],
                'parking_slot' => 'P-'.$wing.substr($flatNumber, -2),
                'monthly_maintenance_amount' => 3800,
                'society_id' => $validated['society_id'],
            ]);

            return $user;
        });

        Auth::login($user);
        $request->session()->regenerate();
        $request->session()->put('current_society_id', $user->getRawOriginal('society_id'));
        app(ActivityLogger::class)->record('registered', 'Resident account registered.', actor: $user);

        return redirect()->route('dashboard')->with('success', 'Your resident account is ready.');
    }

    public function sendResetLink(Request $request): RedirectResponse
    {
        $request->validate(['email' => ['required', 'email']]);

        $status = Password::sendResetLink($request->only('email'));
        app(ActivityLogger::class)->record('password_reset_requested', 'Password reset requested.');
        if ($status === Password::RESET_LINK_SENT || $status === Password::INVALID_USER) {
            return back()->with('status', 'If an account matches that email, a password reset link has been sent.');
        }

        return back()->withErrors(['email' => __($status)]);
    }

    public function resetPassword(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'token' => ['required'],
            'email' => ['required', 'email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $status = Password::reset($credentials, function (User $user, string $password) {
            $user->forceFill([
                'password' => $password,
                'remember_token' => Str::random(60),
            ])->save();

            event(new PasswordReset($user));
            app(ActivityLogger::class)->record('password_reset', 'Password was reset.', actor: $user);
        });

        if ($status === Password::PASSWORD_RESET) {
            return redirect()->route('login')->with('success', 'Your password has been reset. You can now sign in.');
        }

        return back()->withErrors(['email' => __($status)]);
    }

    public function logout(Request $request): RedirectResponse
    {
        app(ActivityLogger::class)->record('logout', 'User signed out.', actor: $request->user());
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('success', 'You have been signed out.');
    }
}
