<?php

namespace App\Services\Admin;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AdminMediaService
{
    public function upload(UploadedFile $file, string $kind, int $actor): array
    {
        $mime = $file->getMimeType();
        $extensions = $kind === 'image' ? ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'] : ['video/mp4' => 'mp4', 'video/quicktime' => 'mov', 'video/webm' => 'webm'];
        if (! isset($extensions[$mime]) || $file->getSize() > ($kind === 'image' ? 10 : 50) * 1024 * 1024) {
            throw ValidationException::withMessages(['file' => 'Tipo o tamaño de archivo no permitido. Imágenes: JPG, PNG o WebP hasta 10 MB; videos: MP4, MOV o WebM hasta 50 MB.']);
        }
        if ($kind === 'image') {
            $dimensions = @getimagesize($file->getRealPath());
            if (! $dimensions || $dimensions[0] > 12000 || $dimensions[1] > 12000 || $dimensions[0] * $dimensions[1] > 40000000) {
                throw ValidationException::withMessages(['file' => 'La imagen no es válida o excede las dimensiones admitidas.']);
            }
        }
        $id = (string) Str::uuid();
        $path = 'admin-media/'.$id.'.'.$extensions[$mime];
        if (! Storage::disk('local')->put($path, file_get_contents($file->getRealPath()))) {
            throw ValidationException::withMessages(['file' => 'No se pudo guardar el archivo.']);
        }
        try {
            DB::table('admin_media')->insert(['id' => $id, 'path' => $path, 'kind' => $kind, 'mime_type' => $mime, 'size' => $file->getSize(), 'created_by' => $actor, 'created_at' => now(), 'updated_at' => now()]);
        } catch (\Throwable $e) {
            Storage::disk('local')->delete($path);
            throw $e;
        }

        return ['id' => $id, 'url' => $this->url($id)];
    }

    public function url(string $id): string
    {
        return URL::temporarySignedRoute('admin.media.download', now()->addMinutes(30), ['media' => $id]);
    }

    public function present(?string $url): ?string
    {
        if ($url && preg_match('~/admin/media/([a-f0-9-]{36})(?:\?|$)~i', $url, $match)) {
            return DB::table('admin_media')->where('id', $match[1])->exists() ? $this->url($match[1]) : null;
        }

        return $url;
    }

    public function assertReference(?string $url, string $kind): void
    {
        if (! $url || ! preg_match('~/admin/media/([a-f0-9-]{36})(?:\?|$)~i', $url, $match)) {
            return;
        }
        $row = DB::table('admin_media')->where('id', $match[1])->lockForUpdate()->first();
        if (! $row || $row->kind !== $kind || ! Storage::disk('local')->exists($row->path)) {
            throw ValidationException::withMessages([$kind === 'video' ? 'video_url' : 'image_url' => 'El archivo no está disponible o su tipo no corresponde. Vuelve a subirlo antes de guardar.']);
        }
    }

    public function prune(): int
    {
        $count = 0;
        foreach (DB::table('admin_media')->where('created_at', '<', now()->subDay())->cursor() as $media) {
            $count += DB::transaction(function () use ($media) {
                $row = DB::table('admin_media')->where('id', $media->id)->lockForUpdate()->first();
                if (! $row) {
                    return 0;
                }
                foreach (['employees' => 'image_url', 'products' => 'image_url', 'equipments' => 'image_url', 'event_types' => 'image_url', 'packages' => 'video_url'] as $table => $column) {
                    if (DB::table($table)->where($column, 'like', '%/admin/media/'.$media->id.'%')->exists()) {
                        return 0;
                    }
                }
                if (! Storage::disk('local')->delete($row->path)) {
                    return 0;
                }
                DB::table('admin_media')->where('id', $media->id)->delete();

                return 1;
            });
        }

        return $count;
    }
}
