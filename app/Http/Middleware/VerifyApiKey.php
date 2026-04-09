<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyApiKey
{
    public function handle(Request $request, Closure $next): Response
    {
        $provided = (string) $request->input('api_key', '');
        $expected = (string) config('herold.api_key', '');

        if ($expected === '' || ! hash_equals($expected, $provided)) {
            abort(401, 'Invalid API key');
        }

        return $next($request);
    }
}
