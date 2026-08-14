<?php

use Illuminate\Support\Facades\Schedule;

/*
 | Scheduled tasks (run by `php artisan schedule:run` via cron — see deploy/crontab.txt).
 */

// Nightly: flag past-due unpaid schedules as overdue (per tenant).
Schedule::command('contributions:sweep-overdue')->dailyAt('01:00');

// Daily: send contribution reminders at the configured offsets (per tenant).
Schedule::command('contributions:remind')->dailyAt('08:00');

// Nightly: back up central + all tenant databases, logged to the DR register.
Schedule::command('mxconnect:backup')->dailyAt('02:30');

// Daily: refresh public offer summaries for the comparator.
Schedule::command('mxconnect:sync-offers')->dailyAt('03:15');

// Daily: SaaS billing cycle (issue period invoices, flag overdue).
Schedule::command('mxconnect:billing-run')->dailyAt('04:00');
