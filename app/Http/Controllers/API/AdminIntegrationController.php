<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Services\Admin\AdminEventService;
use App\Services\Admin\AdminPackageService;
use App\Services\Inventory\IdempotencyService;
use App\Services\Inventory\InventoryException;
use Illuminate\Http\Request;

class AdminIntegrationController extends Controller
{
    private function write(Request $request, string $operation, callable $callback, int $status = 200)
    {
        $request->validate(['page' => ['sometimes', 'integer', 'min:1']]);
        $sort = function ($value) use (&$sort) {
            if (! is_array($value)) {
                return $value;
            } if (! array_is_list($value)) {
                ksort($value);
            }

            return array_map($sort, $value);
        };
        try {
            $result = app(IdempotencyService::class)->run($request->user()->id, $request->header('Idempotency-Key'), $operation, $sort($request->all()), $callback);
        } catch (InventoryException $e) {
            if ($e->errorCode === 'IDEMPOTENCY_KEY_CONFLICT') {
                throw new InventoryException($e->getMessage(), $e->errorCode, $e->errors, 409);
            }
            throw $e;
        }

        return response()->json($result, $status);
    }

    public function savePackage(Request $request, ?int $id = null)
    {
        return $this->write($request, 'admin:package:'.($id ?? 'create'), fn () => app(AdminPackageService::class)->save($request->all(), $request->user()->id, $id), $id ? 200 : 201);
    }

    public function updateEvent(Request $request, int $id)
    {
        return $this->write($request, 'admin:event:'.$id.':update', fn () => app(AdminEventService::class)->update($id, $request->all(), $request->user()->id));
    }

    public function employees(Request $request, int $id)
    {
        return $this->write($request, 'admin:event:'.$id.':employees', fn () => app(AdminEventService::class)->employees($id, $request->all(), $request->user()->id));
    }

    public function transition(Request $request, int $id)
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:2000']]);
        $action = $request->route('transition');

        return $this->write($request, 'admin:event:'.$id.':'.$action, fn () => app(AdminEventService::class)->transition($id, $action, $data['reason'], $request->user()->id));
    }

    public function destroyEvent(Request $request, int $id)
    {
        return $this->write($request, 'admin:event:'.$id.':cancel', fn () => app(AdminEventService::class)->transition($id, 'cancel', $request->input('reason', 'Baja administrativa solicitada'), $request->user()->id));
    }
}
