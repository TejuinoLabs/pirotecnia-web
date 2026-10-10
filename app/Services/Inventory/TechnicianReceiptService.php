<?php

namespace App\Services\Inventory;

use Illuminate\Support\Facades\DB;

class TechnicianReceiptService
{
    public function receive(int $id, array $data, int $actor): array
    {
        return DB::transaction(function () use ($id, $data, $actor) {
            $original = InventoryData::get('event_dispatches', $id);
            InventoryData::get('events', $original->event_id, true);
            $dispatch = InventoryData::get('event_dispatches', $id, true);
            abort_unless($dispatch->technician_id === $actor, 403);
            app(\App\Services\Mobile\TechnicianEventService::class)->authorize(\App\Models\User::findOrFail($actor), $dispatch->event_id);
            app(InventoryAccess::class)->authorize(\App\Models\User::findOrFail($actor), 'mobile.receive');
            InventoryData::assert($dispatch->status === 'confirmed', 'Salida no disponible para recepción.');
            InventoryData::assert(! DB::table('technician_receipts')->where('event_dispatch_id', $id)->exists(), 'Recepción ya registrada.');
            $expected = DB::table('event_dispatch_items')->where('event_dispatch_id', $id)->get()->keyBy('id');
            $ids = collect($data['items'])->pluck('dispatch_item_id');
            InventoryData::assert($ids->unique()->count() === $expected->count() && $ids->count() === $expected->count() && $ids->diff($expected->keys())->isEmpty(), 'Incluye cada línea despachada una sola vez.');
            $incident = false;
            foreach ($data['items'] as $item) {
                $line = $expected[$item['dispatch_item_id']];
                InventoryData::assert($item['received_quantity'] >= 0 && $item['received_quantity'] <= (float) $line->quantity, 'Cantidad recibida inválida.');
                $product = \App\Models\Product::findOrFail($line->product_id);
                if (app(\App\Services\Mobile\MobileDispatchService::class)->precision($product) === 0) {
                    InventoryData::assert(\App\Services\Mobile\MobileContract::milli($item['received_quantity']) % 1000 === 0, 'El artículo requiere cantidades enteras.');
                }
                if ($item['status'] === 'received') {
                    InventoryData::assert((float) $item['received_quantity'] === (float) $line->quantity, 'Usa quantity_mismatch para una recepción parcial.');
                }
                if ($item['status'] === 'missing') {
                    InventoryData::assert((float) $item['received_quantity'] === 0.0, 'missing requiere cantidad cero.');
                }
                $incident = $incident || $item['status'] !== 'received';
            }
            $receipt = InventoryData::insert('technician_receipts', ['event_dispatch_id' => $id, 'technician_id' => $actor, 'status' => $incident ? 'quantity_mismatch' : 'received', 'received_at' => now(), 'notes' => $data['notes'] ?? null, 'device_info' => $data['device_info'] ?? null]);
            $incidentIds = [];
            foreach ($data['items'] as $item) {
                if ($item['status'] !== 'received') {
                    $incidentIds[] = InventoryData::insert('mobile_incidents', ['event_id' => $dispatch->event_id, 'dispatch_item_id' => $item['dispatch_item_id'], 'receipt_id' => $receipt, 'category' => $item['status'] === 'missing' ? 'missing_item' : ($item['status'] === 'damaged' ? 'damaged_item' : 'quantity_mismatch'), 'severity' => 'medium', 'description' => $item['notes'] ?? 'Diferencia registrada al recibir el despacho.', 'created_by' => $actor, 'occurred_at' => now(), 'recorded_at' => now(), 'attachment_ids' => []]);
                }
                InventoryData::insert('technician_receipt_items', ['technician_receipt_id' => $receipt, 'dispatch_item_id' => $item['dispatch_item_id'], 'expected_quantity' => $expected[$item['dispatch_item_id']]->quantity, 'received_quantity' => $item['received_quantity'], 'status' => $item['status'], 'notes' => $item['notes'] ?? null]);
            }
            InventoryData::update('event_dispatches', $id, ['status' => 'received', 'received_by' => $actor, 'received_at' => now()]);
            InventoryData::update('events', $dispatch->event_id, ['logistics_status' => 'in_operation']);
            app(AuditService::class)->record($actor, 'technician_received', 'technician_receipt', $receipt, $data);

            return ['receipt_id' => $receipt, 'status' => $incident ? 'quantity_mismatch' : 'received', 'incident_ids' => $incidentIds];
        });
    }
}
