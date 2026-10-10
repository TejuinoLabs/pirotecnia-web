<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote')->everyMinute();

Artisan::command('mobile:prune-attachments', function () {
    $count = 0;
    \Illuminate\Support\Facades\DB::table('mobile_attachments')->where('expires_at', '<=', now())->orderBy('id')->chunkById(100, function ($files) use (&$count) {
        foreach ($files as $file) {
            if (\Illuminate\Support\Facades\Storage::disk('local')->exists($file->path)) {
                if (\Illuminate\Support\Facades\Storage::disk('local')->delete($file->path)) {
                    $count++;
                }
            }
        }
    });
    $this->info('Adjuntos vencidos eliminados: '.$count.'. Se conservan sus metadatos de auditoría.');
})->purpose('Eliminar archivos móviles vencidos conservando referencias y tombstones')->daily()->withoutOverlapping();

Artisan::command('mobile:upgrade-protocol {event : ID del evento} {--apply : Aplicar el plan revisado} {--actor= : ID del administrador} {--expected-version= : Versión revisada del protocolo} {--reason= : Motivo de auditoría}', function () {
    $service = app(\App\Services\Mobile\MobileProtocolUpgradeService::class);
    $event = (int) $this->argument('event');
    if (! $this->option('apply')) {
        $this->line(json_encode($service->plan($event), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        return;
    }
    if (! $this->option('actor') || $this->option('expected-version') === null || ! trim($this->option('reason') ?? '')) {
        $this->error('Aplicar requiere --actor, --expected-version y --reason.');

        return 1;
    }
    $result = $service->apply($event, \App\Models\User::findOrFail((int) $this->option('actor')), (int) $this->option('expected-version'), $this->option('reason'));
    $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
})->purpose('Revisar o actualizar explícitamente el protocolo sin perder registros existentes');
