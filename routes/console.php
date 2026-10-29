<?php

use App\Models\AuditLog;
use App\Models\Page;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('model:prune', ['--model' => [Page::class]])->daily();

Schedule::call(fn () => AuditLog::where('created_at', '<', now()->subDays(config('audit.retention_days')))->delete())
    ->daily()
    ->when(fn () => config('audit.retention_days') !== null);

Schedule::command('passport:purge')->daily();

// Dynamic client registration is open, so drop clients that never completed a sign-in.
Schedule::command('mcp:prune-clients')->daily();
