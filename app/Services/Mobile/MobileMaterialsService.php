<?php

namespace App\Services\Mobile;

use App\Models\User;
use Illuminate\Support\Facades\DB;

class MobileMaterialsService
{
    public function summary(int $event, User $actor): array
    {
        $dispatches = DB::table('event_dispatches')->where('event_id', $event)->where('technician_id', $actor->id)->where('status', '!=', 'cancelled')->orderBy('id')->get();
        $delivered = $dispatches->whereIn('status', ['confirmed', 'received', 'closed'])->pluck('id');
        $receipts = DB::table('technician_receipts')->whereIn('event_dispatch_id', $delivered)->pluck('id');
        $open = DB::table('mobile_incidents')->where('event_id', $event)->where('status', '!=', 'resolved')->count();
        $counts = [
            'required_lines' => DB::table('event_requirements')->where('event_id', $event)->count(),
            'prepared_lines' => DB::table('event_picking_items')->whereIn('event_picking_id', DB::table('event_pickings')->where('event_id', $event)->where('status', '!=', 'cancelled')->select('id'))->where('status', 'confirmed')->whereNotIn('requirement_allocation_id', DB::table('requirement_allocations')->whereIn('status', ['cancelled', 'replaced'])->select('id'))->count(),
            'delivered_lines' => DB::table('event_dispatch_items')->whereIn('event_dispatch_id', $delivered)->count(),
            'verified_lines' => DB::table('technician_receipt_items')->whereIn('technician_receipt_id', $receipts)->count(),
            'open_discrepancies' => $open,
        ];
        $status = 'pending_dispatch';
        if ($dispatches->isNotEmpty() && $dispatches->every(fn ($d) => $d->status === 'closed')) {
            $status = 'reconciled';
        } elseif ($open || DB::table('technician_receipts')->whereIn('id', $receipts)->where('status', 'quantity_mismatch')->exists()) {
            $status = 'has_discrepancies';
        } elseif (DB::table('mobile_return_declarations')->whereIn('dispatch_id', $dispatches->pluck('id'))->whereNull('warehouse_confirmed_at')->exists() || DB::table('event_returns')->whereIn('event_dispatch_id', $dispatches->pluck('id'))->where('status', 'draft')->exists()) {
            $status = 'awaiting_warehouse';
        } elseif (DB::table('events')->where('id', $event)->where('logistics_status', 'awaiting_return')->exists()) {
            $status = 'awaiting_return';
        } elseif (DB::table('event_departure_passes')->where('event_id', $event)->where('technician_id', $actor->id)->exists() && app(DeparturePassService::class)->show($event, $actor)['data']['status'] === 'valid') {
            $status = 'departure_authorized';
        } elseif ($dispatches->contains('status', 'confirmed')) {
            $status = 'ready_to_receive';
        } elseif ($dispatches->isNotEmpty() && $dispatches->every(fn ($d) => $d->status === 'received')) {
            $status = 'received';
        } elseif ($dispatches->isNotEmpty() || DB::table('event_preparations')->where('event_id', $event)->whereIn('status', ['in_progress', 'ready'])->exists() || DB::table('event_pickings')->where('event_id', $event)->exists()) {
            $status = 'preparing';
        }

        return ['materials_status' => $status, 'materials_summary' => $counts];
    }

    public function hasPendingCustody(int $event): bool
    {
        foreach (DB::table('event_dispatches')->where('event_id', $event)->where('status', 'received')->pluck('id') as $id) {
            foreach (app(MobileReturnService::class)->reconciliation($id)['items'] as $item) {
                if (MobileContract::milli($item['quantity_pending']) > 0) {
                    return true;
                }
            }
        }

        return DB::table('mobile_incidents')->where('event_id', $event)->whereIn('category', ['missing_item', 'damaged_item', 'quantity_mismatch'])->where('status', '!=', 'resolved')->exists();
    }
}
