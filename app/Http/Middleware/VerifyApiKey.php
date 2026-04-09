<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyApiKey
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->session()->get('auth.key_verified') !== true) {
            return redirect()->route('login');
        }

        return $next($request);
    }
}
