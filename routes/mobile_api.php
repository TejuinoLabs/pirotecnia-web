<?php

use App\Http\Controllers\API\TechnicianMobileController as Mobile;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:api', 'throttle:120,1'])->prefix('technician')->group(function () {
    Route::get('events', [Mobile::class, 'events']);
    Route::get('sync/bootstrap', [Mobile::class, 'bootstrap']);
    Route::get('attachments/{attachment}', [Mobile::class, 'downloadAttachment'])->whereNumber('attachment');
    Route::patch('step-records/{record}', [Mobile::class, 'correct'])->whereNumber('record');
    Route::get('events/{event}', [Mobile::class, 'event'])->whereNumber('event');
    Route::get('events/{event}/protocol', [Mobile::class, 'protocol'])->whereNumber('event');
    Route::post('events/{event}/steps/{step}/records', [Mobile::class, 'record'])->whereNumber(['event', 'step']);
    Route::get('events/{event}/timeline', [Mobile::class, 'timeline'])->whereNumber('event');
    Route::get('events/{event}/incidents', [Mobile::class, 'incidents'])->whereNumber('event');
    Route::post('events/{event}/incidents', [Mobile::class, 'createIncident'])->whereNumber('event');
    Route::get('events/{event}/return-declarations', [Mobile::class, 'returns'])->whereNumber('event');
    Route::post('events/{event}/return-declarations', [Mobile::class, 'declareReturn'])->whereNumber('event');
    Route::post('events/{event}/attachments', [Mobile::class, 'attachment'])->whereNumber('event');
    Route::post('events/{event}/close', [Mobile::class, 'close'])->whereNumber('event');
    Route::post('events/{event}/steps/{step}/override', [Mobile::class, 'override'])->whereNumber(['event', 'step']);
});
