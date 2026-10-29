<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('cycles:snapshot-active')
    ->dailyAt('23:55')
    ->withoutOverlapping(10);

Schedule::command('tasks:generate-recurring')
    ->dailyAt('00:05')
    ->withoutOverlapping(10);
