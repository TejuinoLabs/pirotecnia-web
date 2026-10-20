<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Services\Inventory\IdempotencyService;
use App\Services\Inventory\InventoryAccess;
use App\Services\Inventory\InventoryException;
use App\Services\Mobile\DeparturePassService;
use App\Services\Mobile\MobileContract;
use Illuminate\Http\Request;

class DeparturePassController extends Controller
{
    public function issue(Request $request, int $event)
    {
        app(InventoryAccess::class)->authorize($request->user(), 'inventory.departure.issue');
        $data = $request->validate(['technician_id' => ['required', 'integer', 'exists:users,id'], 'expires_at' => MobileContract::timestampRule(),
            'dispatches' => ['required', 'array', 'min:1'], 'dispatches.*' => ['array:id,version'],
            'dispatches.*.id' => ['required', 'integer', 'distinct'], 'dispatches.*.version' => ['present', 'nullable', 'integer', 'min:1']]);

        return $this->write($request, 'departure:'.$event.':issue', $data, fn () => app(DeparturePassService::class)->issue($event, $data, $request->user()), 201);
    }

    public function revoke(Request $request, int $event, int $pass)
    {
        app(InventoryAccess::class)->authorize($request->user(), 'inventory.departure.revoke');
        $data = $request->validate(['expected_version' => ['required', 'integer', 'min:1'], 'reason' => ['required', 'string', 'max:2000']]);

        return $this->write($request, 'departure:'.$event.':'.$pass.':revoke', $data, fn () => app(DeparturePassService::class)->revoke($event, $pass, $data, $request->user()), 200);
    }

    private function write(Request $request, string $operation, array $data, callable $callback, int $status)
    {
        \Illuminate\Support\Facades\Validator::make(['key' => $request->header('Idempotency-Key')], ['key' => ['required', 'uuid:4']])->validate();
        try {
            $result = app(IdempotencyService::class)->run($request->user()->id, $request->header('Idempotency-Key'), $operation, $data, $callback);
        } catch (InventoryException $e) {
            if ($e->errorCode === 'IDEMPOTENCY_KEY_CONFLICT') {
                throw new InventoryException($e->getMessage(), $e->errorCode, $e->errors, 409);
            }
            throw $e;
        }

        return response()->json($result, $status);
    }
}
