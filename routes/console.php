<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('queue:work database --stop-when-empty --tries=3 --max-time=50')
    ->everyMinute()
    ->withoutOverlapping();
