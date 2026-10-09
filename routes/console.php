<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('student-surveys:sync')
    ->dailyAt('00:00')
    ->timezone('Asia/Almaty')
    ->withoutOverlapping();
