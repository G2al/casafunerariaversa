<?php

use App\Models\AuditEvent;
use App\Services\DeleteFuneralCase;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('funeral:cleanup-deleted-files', function () {
    $remaining = 0;
    AuditEvent::where('event', 'permanently_deleted')->orderBy('id')->chunkById(100, function ($events) use (&$remaining) {
        foreach ($events as $event) {
            if ($event->changed_fields['pending_files'] ?? []) {
                $remaining += app(DeleteFuneralCase::class)->cleanup($event->id);
            }
        }
    });
    $this->info('Pulizia completata. File ancora da rimuovere: '.$remaining);

    return $remaining ? 1 : 0;
})->purpose('Riprova la rimozione dei file delle pratiche eliminate definitivamente');

Schedule::command('funeral:cleanup-deleted-files')->hourly()->withoutOverlapping();
