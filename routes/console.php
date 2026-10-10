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
