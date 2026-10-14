<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EstablishSocietyContext
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if ($user?->role === 'super_admin') {
            $request->session()->forget('current_society_id');
        } elseif ($user) {
            $request->session()->put('current_society_id', $user->getRawOriginal('society_id'));
            $request->session()->forget('active_society_id');
        }

        return $next($request);
    }
}
