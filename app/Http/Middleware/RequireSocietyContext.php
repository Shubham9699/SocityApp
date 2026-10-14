<?php

namespace App\Http\Middleware;

use App\Models\Society;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireSocietyContext
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->role === 'super_admin') {
            $societyId = $request->session()->get('active_society_id');
            abort_unless($societyId && Society::whereKey($societyId)->where('status', 'active')->exists(), 403, 'Select an active society workspace first.');
        }

        return $next($request);
    }
}
