<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('tasks:delete-approved-tasks')
    ->daily()
    ->at('00:00');

Schedule::command('tasks:send-reminders')
    ->daily()
    ->at('09:00');



