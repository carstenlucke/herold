<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyCronAuth
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = (string) $request->getUser();
        $pass = (string) $request->getPassword();

        if (! hash_equals((string) env('CRON_USER'), $user) || ! hash_equals((string) env('CRON_PASSWORD'), $pass)) {
            return response('Unauthorized', 401, ['WWW-Authenticate' => 'Basic realm="Herold Cron"']);
        }

        return $next($request);
    }
}
