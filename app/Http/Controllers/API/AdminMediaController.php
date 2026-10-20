<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Services\Admin\AdminMediaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class AdminMediaController extends Controller
{
    public function store(Request $request)
    {
        $data = $request->validate(['kind' => ['required', 'in:image,video'], 'file' => ['required', 'file', 'max:51200']]);

        return response()->json(app(AdminMediaService::class)->upload($request->file('file'), $data['kind'], $request->user()->id), 201);
    }

    public function download(string $media)
    {
        $row = DB::table('admin_media')->where('id', $media)->first();
        abort_unless($row && Storage::disk('local')->exists($row->path), 404);

        return response()->file(Storage::disk('local')->path($row->path), ['Content-Type' => $row->mime_type, 'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
    }
}
