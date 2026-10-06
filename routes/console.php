<?php

use App\Services\AttemptService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
 * Hand in quiz and examination attempts whose time ran out while the
 * student was away. Attempts are also closed whenever they are next opened,
 * so this only keeps scores and lists up to date.
 */
Artisan::command('attempts:close-expired', function (AttemptService $attempts) {
    $this->info($attempts->closeExpired().' attempt(s) handed in.');
})->purpose('Hand in attempts whose time is up');

Schedule::command('attempts:close-expired')->everyMinute()->withoutOverlapping();
