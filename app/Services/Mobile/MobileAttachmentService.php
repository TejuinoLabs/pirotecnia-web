<?php

namespace App\Services\Mobile;

use App\Services\Inventory\InventoryData;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class MobileAttachmentService
{
    public function upload(int $event, int $actor, string $operation, string $type, UploadedFile $file): array
    {
        $path = $file->store('mobile/'.$event, 'local');
        if (! $path) {
            throw new \RuntimeException('No fue posible guardar el adjunto.');
        }
        try {
            $id = InventoryData::insert('mobile_attachments', ['event_id' => $event, 'created_by' => $actor, 'client_operation_id' => $operation, 'entity_type' => $type, 'path' => $path, 'mime_type' => $file->getMimeType(), 'size' => $file->getSize(), 'sha256' => hash_file('sha256', $file->getRealPath()), 'expires_at' => now()->addDays(config('mobile.attachment_retention_days'))]);

            return ['data' => $this->present(InventoryData::get('mobile_attachments', $id))];
        } catch (\Throwable $error) {
            Storage::disk('local')->delete($path);
            throw $error;
        }
    }

    public function present(object $row): array
    {
        return ['id' => $row->id, 'event_id' => $row->event_id, 'entity_type' => $row->entity_type, 'mime_type' => $row->mime_type, 'size' => $row->size, 'expires_at' => MobileContract::utc($row->expires_at)];
    }

    public function link(array $ids, int $event, int $actor, string $type, int $entity, array $allowed = []): void
    {
        foreach ($ids as $id) {
            $file = DB::table('mobile_attachments')->where('id', $id)->lockForUpdate()->first();
            if (! $file || $file->event_id !== $event || $file->created_by !== $actor || $file->entity_type !== $type || $file->expires_at <= now()->toDateTimeString() || ($allowed && ! in_array($file->mime_type, $allowed, true))) {
                throw ValidationException::withMessages(['attachment_ids' => ['El adjunto no pertenece al evento/autor, no está permitido o ya expiró.']]);
            }
            MobileContract::conflict($file->entity_id === null, 'El adjunto ya está asociado a otro registro.', 'ATTACHMENT_ALREADY_LINKED');
            InventoryData::update('mobile_attachments', $id, ['entity_id' => $entity]);
        }
    }
}
