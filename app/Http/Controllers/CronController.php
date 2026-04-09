<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Artisan;

class CronController extends Controller
{
    public function work(): JsonResponse
    {
        Artisan::call('queue:work database --stop-when-empty --tries=3 --max-time=50');

        return response()->json(['ok' => true, 'output' => Artisan::output()]);
    }
}
