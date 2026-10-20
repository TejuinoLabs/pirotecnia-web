<?php

namespace App\Services\Inventory;

use RuntimeException;

class InventoryException extends RuntimeException
{
    public function __construct(string $message, public string $errorCode = 'INVALID_LOGISTICS_STATE', public array $errors = [], public int $status = 422)
    {
        parent::__construct($message);
    }

    public function render($request)
    {
        if ($request->is('api/*') || $request->expectsJson()) {
            return response()->json(['message' => $this->getMessage(), 'code' => $this->errorCode, 'errors' => $this->errors], $this->status);
        }

        return redirect()->back()->withInput()->withErrors(['inventory' => $this->getMessage()]);
    }
}
