<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Services\Inventory\IdempotencyService;
use App\Services\Inventory\InventoryData;
use App\Services\Inventory\InventoryException;
use App\Services\Mobile\MobileAttachmentService;
use App\Services\Mobile\MobileContract as Contract;
use App\Services\Mobile\MobileOperationService;
use App\Services\Mobile\MobileReturnService;
use App\Services\Mobile\MobileSyncService;
use App\Services\Mobile\TechnicianEventService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class TechnicianMobileController extends Controller
{
    private function validate(Request $request, array $rules): array
    {
        $data = Validator::make($request->all(), ['client_operation_id' => ['required', 'uuid'], ...$rules])->validate();
        Validator::make(['key' => $request->header('Idempotency-Key')], ['key' => ['required', 'regex:/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', 'in:'.$data['client_operation_id']]])->validate();

        return $data;
    }

    private function write(Request $request, callable $callback, int $status = 201, array $extra = [])
    {
        $payload = ['path' => $request->path(), 'method' => $request->method(), 'payload' => $request->except('file'), ...$extra];
        $sort = function ($value) use (&$sort) {
            if (! is_array($value)) {
                return $value;
            } if (! array_is_list($value)) {
                ksort($value);
            }

return array_map($sort, $value);
        };
        try {
            $result = app(IdempotencyService::class)->run($request->user()->id, $request->header('Idempotency-Key'), 'mobile:v1', $sort($payload), $callback);
        } catch (InventoryException $e) {
            if ($e->errorCode === 'IDEMPOTENCY_KEY_CONFLICT') {
                throw new InventoryException($e->getMessage(), $e->errorCode, $e->errors, 409);
            } throw $e;
        }

        return response()->json($result, $status);
    }

    private function times(): array
    {
        return ['occurred_at' => Contract::timestampRule(), 'recorded_at' => Contract::timestampRule()];
    }

    private function files(): array
    {
        return ['attachment_ids' => ['sometimes', 'array', 'max:10'], 'attachment_ids.*' => ['integer', 'distinct', 'exists:mobile_attachments,id']];
    }

    public function events(Request $request)
    {
        $q = $request->validate(['status' => ['sometimes', 'in:upcoming,in_progress,completed'], 'page' => ['sometimes', 'integer', 'min:1'], 'search' => ['nullable', 'string', 'max:255'], 'from' => ['nullable', ...array_slice(Contract::timestampRule(), 1)], 'to' => ['nullable', ...array_slice(Contract::timestampRule(), 1), ...(! empty($request->input('from')) ? ['after_or_equal:from'] : [])]]);
        $service = app(TechnicianEventService::class);
        $query = DB::table('events')->whereIn('id', $service->assignedIds($request->user()->id));
        if (! empty($q['from'])) {
            $query->where('event_date', '>=', \Carbon\CarbonImmutable::parse($q['from'])->setTimezone(config('mobile.event_timezone'))->toDateTimeString());
        }
        if (! empty($q['to'])) {
            $query->where('event_date', '<=', \Carbon\CarbonImmutable::parse($q['to'])->setTimezone(config('mobile.event_timezone'))->toDateTimeString());
        }
        if (! empty($q['search'])) {
            $query->where(fn ($w) => $w->where('client_name', 'like', '%'.$q['search'].'%')->orWhere('event_address', 'like', '%'.$q['search'].'%'));
        }
        if (($q['status'] ?? null) === 'completed') {
            $query->where(fn ($w) => $w->where('logistics_status', 'closed')->orWhereIn('id', DB::table('mobile_event_protocols')->where('status', 'closed')->select('event_id')));
        }
        if (($q['status'] ?? null) === 'in_progress') {
            $query->whereIn('logistics_status', ['in_operation', 'awaiting_return'])->whereNotIn('id', DB::table('mobile_event_protocols')->where('status', 'closed')->select('event_id'));
        }
        if (($q['status'] ?? null) === 'upcoming') {
            $query->where('event_date', '>=', now(config('mobile.event_timezone'))->toDateTimeString())->whereNotIn('logistics_status', ['in_operation', 'awaiting_return', 'closed'])->whereNotIn('id', DB::table('mobile_event_protocols')->where('status', 'closed')->select('event_id'));
        }

        return Contract::page($query->orderBy('event_date')->orderBy('id')->paginate(config('mobile.page_size')), fn ($row) => $service->summary($row, $request->user()));
    }

    public function event(Request $request, int $event)
    {
        return ['data' => app(TechnicianEventService::class)->detail($event, $request->user())];
    }

    public function protocol(Request $request, int $event)
    {
        return ['data' => app(TechnicianEventService::class)->protocol($event, $request->user())];
    }

    public function record(Request $request, int $event, int $step)
    {
        app(TechnicianEventService::class)->authorize($request->user(), $event, 'mobile.record');
        $data = $this->validate($request, [...$this->times(), ...$this->files(), 'protocol_version' => ['required', 'integer', 'min:1'], 'capture_mode' => ['required', 'in:automatic,manual'], 'manual_reason' => ['required_if:capture_mode,manual', 'nullable', 'string', 'max:2000'], 'notes' => ['nullable', 'string', 'max:2000']]);

        return $this->write($request, fn () => app(MobileOperationService::class)->record($event, $step, $data, $request->user()));
    }

    public function correct(Request $request, int $record)
    {
        $r = InventoryData::get('mobile_step_records', $record);
        app(TechnicianEventService::class)->authorize($request->user(), $r->event_id, 'mobile.correct_own');
        abort_unless($r->recorded_by === $request->user()->id, 403);
        $data = $this->validate($request, ['expected_revision' => ['required', 'integer', 'min:1'], 'occurred_at' => ['sometimes', ...array_slice(Contract::timestampRule(), 1)], 'notes' => ['nullable', 'string', 'max:2000'], 'correction_reason' => ['required', 'string', 'max:2000']]);

        return $this->write($request, fn () => app(MobileOperationService::class)->correct($record, $data, $request->user()), 200);
    }

    public function timeline(Request $request, int $event)
    {
        $request->validate(['page' => ['sometimes', 'integer', 'min:1']]);

        return app(MobileSyncService::class)->timeline($event, $request->user());
    }

    public function createIncident(Request $request, int $event)
    {
        app(TechnicianEventService::class)->authorize($request->user(), $event, 'mobile.incident');
        $data = $this->validate($request, [...$this->times(), ...$this->files(), 'category' => ['required', 'in:missing_item,damaged_item,quantity_mismatch,delay,logistics_change,safety,other'], 'severity' => ['required', 'in:low,medium,high,critical'], 'dispatch_item_id' => ['nullable', 'integer'], 'step_id' => ['nullable', 'integer'], 'description' => ['required', 'string', 'max:2000']]);

        return $this->write($request, fn () => app(MobileOperationService::class)->incident($event, $data, $request->user()));
    }

    public function incidents(Request $request, int $event)
    {
        app(TechnicianEventService::class)->authorize($request->user(), $event);
        $data = $request->validate(['status' => ['sometimes', 'in:open,in_review,resolved'], 'page' => ['sometimes', 'integer', 'min:1']]);
        $query = DB::table('mobile_incidents')->where('event_id', $event);
        if (isset($data['status'])) {
            $query->where('status', $data['status']);
        }

        return Contract::page($query->orderBy('occurred_at')->orderBy('id')->paginate(20), fn ($r) => Contract::dates((array) $r));
    }

    public function declareReturn(Request $request, int $event)
    {
        app(TechnicianEventService::class)->authorize($request->user(), $event, 'mobile.return');
        $rules = [...$this->times(), 'dispatch_id' => ['required', 'integer'], 'expected_revision' => ['required', 'integer', 'min:1'], 'notes' => ['nullable', 'string', 'max:2000'], 'items' => ['required', 'array', 'min:1', 'max:500'], 'items.*' => ['array:dispatch_item_id,quantity_consumed,quantity_returned,quantity_damaged,quantity_lost,notes'], 'items.*.dispatch_item_id' => ['required', 'integer', 'distinct'], 'items.*.notes' => ['nullable', 'string', 'max:2000']];
        foreach (['consumed', 'returned', 'damaged', 'lost'] as $name) {
            $rules['items.*.quantity_'.$name] = Contract::quantityRule();
        }
        $data = $this->validate($request, $rules);

        return $this->write($request, fn () => app(MobileReturnService::class)->declare($event, $data, $request->user()));
    }

    public function returns(Request $request, int $event)
    {
        return app(MobileReturnService::class)->list($event, $request->user());
    }

    public function bootstrap(Request $request)
    {
        $data = $request->validate(['updated_since' => ['nullable', ...array_slice(Contract::timestampRule(), 1)], 'cursor' => ['nullable', 'string', 'max:65535']]);

        return app(MobileSyncService::class)->bootstrap($request->user(), $data['cursor'] ?? null);
    }

    public function attachment(Request $request, int $event)
    {
        app(TechnicianEventService::class)->authorize($request->user(), $event, 'mobile.attach');
        $data = $this->validate($request, ['entity_type' => ['required', 'in:step_record,incident'], 'file' => ['required', 'file', 'max:'.config('mobile.attachment_max_kb'), 'mimetypes:'.implode(',', config('mobile.attachment_mimes'))]]);
        $file = $request->file('file');

        return $this->write($request, fn () => app(MobileAttachmentService::class)->upload($event, $request->user()->id, $data['client_operation_id'], $data['entity_type'], $file), 201, ['file_sha256' => hash_file('sha256', $file->getRealPath()), 'file_size' => $file->getSize(), 'mime_type' => $file->getMimeType()]);
    }

    public function downloadAttachment(Request $request, int $attachment)
    {
        $file = InventoryData::get('mobile_attachments', $attachment);
        app(TechnicianEventService::class)->authorize($request->user(), $file->event_id);
        abort_unless($file->expires_at > now()->toDateTimeString() && Storage::disk('local')->exists($file->path), 404);

        return Storage::disk('local')->download($file->path, 'evidencia-'.$file->id.'.'.(['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'application/pdf' => 'pdf'][$file->mime_type]), ['Content-Type' => $file->mime_type, 'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
    }

    public function close(Request $request, int $event)
    {
        app(TechnicianEventService::class)->authorize($request->user(), $event, 'mobile.close');
        $data = $this->validate($request, [...$this->times(), 'protocol_version' => ['required', 'integer', 'min:1'], 'notes' => ['nullable', 'string', 'max:2000']]);

        return $this->write($request, fn () => app(MobileOperationService::class)->close($event, $data, $request->user()), 200);
    }

    public function override(Request $request, int $event, int $step)
    {
        abort_unless(app(TechnicianEventService::class)->canOverride($request->user()), 403);
        app(TechnicianEventService::class)->authorize($request->user(), $event, 'mobile.override');
        $data = $this->validate($request, ['protocol_version' => ['required', 'integer', 'min:1'], 'reason' => ['required', 'string', 'max:2000']]);

        return $this->write($request, fn () => app(MobileOperationService::class)->override($event, $step, $data, $request->user()));
    }
}
