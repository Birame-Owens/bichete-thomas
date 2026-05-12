<?php

use App\Console\Commands\DispatchReviewInvitations;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Cron quotidien a 10h : envoie les invitations avis WhatsApp pour les
// reservations terminees la veille. Le worker queue Redis doit tourner
// en parallele pour consommer les jobs dispatches.
Schedule::command(DispatchReviewInvitations::class)->dailyAt('10:00');
