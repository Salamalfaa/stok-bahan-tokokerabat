<?php

use App\Models\VoiceAnnouncement;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::call(function (): void {
    VoiceAnnouncement::query()
        ->whereNotNull('order_id')
        ->where('expires_at', '<=', now())
        ->delete();
})->hourly()->name('prune-expired-order-voice-announcements');
