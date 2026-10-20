<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Services\Mobile\MobileContract;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    private function present($notification): array
    {
        return ['id' => $notification->id, 'title' => $notification->data['title'] ?? 'Aviso',
            'data' => $notification->data,
            'body' => $notification->data['body'] ?? '', 'event_id' => $notification->data['event_id'] ?? null,
            'created_at' => MobileContract::utc($notification->created_at), 'read_at' => MobileContract::utc($notification->read_at)];
    }

    public function index(Request $request)
    {
        $request->validate(['page' => ['sometimes', 'integer', 'min:1']]);

        return MobileContract::page($request->user()->notifications()->orderByDesc('created_at')->orderByDesc('id')->paginate(20), fn ($row) => $this->present($row));
    }

    public function read(Request $request, string $notification)
    {
        $row = $request->user()->notifications()->whereKey($notification)->firstOrFail();
        $row->markAsRead();

        return ['data' => $this->present($row)];
    }
}
