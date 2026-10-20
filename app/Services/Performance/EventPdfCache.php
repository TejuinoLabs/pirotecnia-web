<?php

namespace App\Services\Performance;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

class EventPdfCache
{
    public function store(int $eventId, string $fingerprint, callable $render): string
    {
        $path = 'pdf/event_'.$eventId.'.pdf';
        $key = 'event-pdf:'.$eventId;
        $disk = Storage::disk('public');
        $isFresh = fn () => Cache::get($key) === $fingerprint && $disk->exists($path);
        if (! $isFresh()) {
            Cache::lock($key.':lock', 60)->block(15, function () use ($key, $fingerprint, $path, $render, $disk, $isFresh) {
                if ($isFresh()) {
                    return;
                }
                if (! $disk->put($path, $render())) {
                    throw new \RuntimeException('No se pudo guardar el PDF del evento.');
                }
                Cache::put($key, $fingerprint, now()->addSeconds(config('performance.pdf_cache_seconds')));
            });
        }

        return asset('storage/'.$path);
    }
}
