<?php

namespace App\Services\Purchases;

use App\Models\Product;
use App\Services\Inventory\InventoryAccess;
use App\Services\Inventory\ProductCatalogService;
use App\Services\Inventory\PurchaseReceiptService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PurchaseDocumentImportService
{
    public function preview(UploadedFile $file, int $actor): array
    {
        $hash = hash_file('sha256', $file->getRealPath());
        $existing = DB::table('purchase_document_imports')->where('sha256', $hash)->first();
        if (! $existing) {
            $extracted = app(PurchaseDocumentParser::class)->read($file->getRealPath());
            $path = 'purchase-documents/'.$hash.'.pdf';
            if (! Storage::disk('local')->put($path, file_get_contents($file->getRealPath()))) {
                throw ValidationException::withMessages(['document' => 'No se pudo guardar el documento. Intenta nuevamente.']);
            }
            DB::table('purchase_document_imports')->insertOrIgnore(['token' => (string) Str::uuid(), 'sha256' => $hash,
                'path' => $path, 'extracted' => json_encode($extracted, JSON_THROW_ON_ERROR), 'created_by' => $actor,
                'created_at' => now(), 'updated_at' => now()]);
            $existing = DB::table('purchase_document_imports')->where('sha256', $hash)->first();
        }
        $data = json_decode($existing->extracted, true, flags: JSON_THROW_ON_ERROR);
        $normalize = static fn ($name) => Str::lower(Str::ascii(preg_replace('/\s+/u', ' ', trim($name))));
        $products = Product::where('active', true)->get()->groupBy(fn ($product) => $normalize($product->display_name));
        foreach ($data['items'] as &$item) {
            $matches = $products->get($normalize($item['description']));
            $item['product_id'] = $matches?->count() === 1 ? $matches->first()->id : null;
        }
        unset($item);

        return $data + ['token' => $existing->token, 'purchase_id' => $existing->purchase_id];
    }

    public function createProduct(string $token, array $payload, int $actor): array
    {
        $data = Validator::make($payload, [
            'line_index' => ['required', 'integer', 'min:0'],
            'name' => ['required', 'string', 'max:255'],
            'product_role_id' => ['required', 'integer', 'exists:product_roles,id'],
            'unit' => ['required', 'string', 'max:100'],
        ])->validate();
        $data['name'] = preg_replace('/\s+/u', ' ', trim($data['name']));
        if ($data['name'] === '') {
            throw ValidationException::withMessages(['name' => 'Escribe el nombre del producto.']);
        }

        return DB::transaction(function () use ($token, $data, $actor) {
            $document = DB::table('purchase_document_imports')->where('token', $token)->lockForUpdate()->first();
            abort_unless($document, 404);
            $extracted = json_decode($document->extracted, true, flags: JSON_THROW_ON_ERROR);
            if ($document->purchase_id || ! isset($extracted['items'][$data['line_index']])) {
                throw ValidationException::withMessages(['document' => 'El concepto no está disponible para crear un producto.']);
            }
            $normalize = static fn ($name) => Str::lower(Str::ascii(preg_replace('/\s+/u', ' ', trim($name))));
            $matches = Product::where('active', true)->get()->filter(fn ($product) => $normalize($product->display_name) === $normalize($data['name']));
            if ($matches->count() > 1) {
                throw ValidationException::withMessages(['name' => 'Hay varios productos con este nombre. Selecciona uno de las coincidencias.']);
            }
            // Repeated clicks reuse the existing product without changing its catalog data.
            if ($matches->count() === 1) {
                return app(ProductCatalogService::class)->show($matches->first()->id);
            }

            return app(ProductCatalogService::class)->save([
                'name' => $data['name'], 'product_role_id' => $data['product_role_id'], 'unit' => $data['unit'],
                'active' => true, 'tracks_inventory' => true,
                'purchase_cost' => $extracted['items'][$data['line_index']]['unit_cost'],
            ], $actor);
        });
    }

    public function register(string $token, array $payload, $user): array
    {
        return DB::transaction(function () use ($token, $payload, $user) {
            $document = DB::table('purchase_document_imports')->where('token', $token)->lockForUpdate()->first();
            abort_unless($document, 404);
            if ($document->purchase_id) {
                return app(PurchaseReceiptService::class)->show($document->purchase_id);
            }
            $extracted = json_decode($document->extracted, true, flags: JSON_THROW_ON_ERROR);
            $data = Validator::make($payload, [
                'supplier_name' => ['required', 'string', 'max:255'],
                'status' => ['required', 'in:draft,ordered,received'],
                'warehouse_location_id' => ['nullable', 'integer'],
                'notes' => ['nullable', 'string', 'max:1500'],
                'items' => ['required', 'array', 'list', 'size:'.count($extracted['items'])],
                'items.*.product_id' => ['required', 'integer', 'distinct', 'exists:products,id'],
            ])->validate();
            foreach ($data['items'] as $i => &$item) {
                if (! Product::whereKey($item['product_id'])->where('active', true)->exists()) {
                    throw ValidationException::withMessages(["items.$i.product_id" => 'Selecciona un producto activo para cada concepto.']);
                }
                $item = ['product_id' => $item['product_id'], 'quantity' => $extracted['items'][$i]['quantity'], 'unit_cost' => $extracted['items'][$i]['unit_cost']];
            }
            unset($item);
            if ($data['status'] === 'received') {
                app(InventoryAccess::class)->authorize($user, 'purchases.receive');
            }
            $data['purchase_date'] = $extracted['purchase_date'];
            $data['tax'] = $extracted['tax'];
            $data['notes'] = trim(($data['notes'] ?? '')."\nImportado de presupuesto PDF. SHA-256: ".$document->sha256);
            $result = app(PurchaseReceiptService::class)->create($data, $user->id);
            DB::table('purchase_document_imports')->where('id', $document->id)->update(['purchase_id' => $result['purchase']['id'], 'updated_at' => now()]);

            return $result;
        });
    }
}
