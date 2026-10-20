<?php

namespace App\Services\Purchases;

use Illuminate\Validation\ValidationException;
use Smalot\PdfParser\Parser;

class PurchaseDocumentParser
{
    public function read(string $path): array
    {
        try {
            $text = (new Parser)->parseFile($path)->getText();
        } catch (\Throwable $e) {
            throw ValidationException::withMessages(['document' => 'No se pudo leer el PDF. Usa un documento con texto seleccionable, sin contraseña.']);
        }

        return $this->parse($text);
    }

    public function parse(string $text): array
    {
        $text = preg_replace('/\s+/u', ' ', $text);
        if (! preg_match('/Presupuesto de Venta/iu', $text)
            || ! preg_match('/Cant\s*Producto\s*Precio Unitario\s*Importe\s+(.+?)\s+Total\s*\$\s*([\d,]+\.\d{2})/iu', $text, $table)) {
            $this->invalid('Formato no reconocido. Usa un presupuesto con cantidad, producto, precio unitario, importe y total. Los escaneos requieren OCR.');
        }
        $money = static fn ($value) => (int) round((float) str_replace(',', '', $value) * 100);
        $items = [];
        $remaining = trim($table[1]);
        while ($remaining !== '') {
            if (! preg_match('/^(\d+(?:\.\d{1,3})?)\s+(.+?)\s*\$\s*([\d,]+\.\d{2})\s*\$\s*([\d,]+\.\d{2})(?:\s+|$)/u', $remaining, $row)) {
                $this->invalid('No se pudieron identificar todos los conceptos del documento.');
            }
            $quantity = (float) $row[1];
            $cost = $money($row[3]);
            $amount = $money($row[4]);
            if ($quantity <= 0 || (int) round($quantity * $cost) !== $amount) {
                $this->invalid('Un importe no coincide con su cantidad y precio unitario.');
            }
            $items[] = ['description' => trim($row[2]), 'quantity' => $quantity, 'unit_cost' => $cost / 100, 'amount' => $amount / 100];
            $remaining = trim(substr($remaining, strlen($row[0])));
        }
        $total = $money($table[2]);
        if (! $items || (int) round(array_sum(array_column($items, 'amount')) * 100) !== $total) {
            $this->invalid('El total del documento no coincide con sus conceptos.');
        }
        if (preg_match('/Total de Productos:\s*(\d+)\s*-\s*Total de Art[ií]culos\s*(\d+(?:\.\d+)?)/iu', $text, $counts)
            && ((int) $counts[1] !== count($items) || abs((float) $counts[2] - array_sum(array_column($items, 'quantity'))) > 0.0001)) {
            $this->invalid('El número de conceptos o artículos no coincide con el resumen del documento.');
        }
        $months = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
        $date = null;
        if (preg_match('/(\d{1,2}) de ('.implode('|', $months).') de (\d{4})/iu', $text, $parts)) {
            $month = array_search(mb_strtolower($parts[2]), $months, true) + 1;
            if (checkdate($month, (int) $parts[1], (int) $parts[3])) {
                $date = sprintf('%04d-%02d-%02d', $parts[3], $month, $parts[1]);
            }
        }
        if (! $date) {
            $this->invalid('No se pudo identificar una fecha válida en el documento.');
        }

        return ['document_type' => 'Presupuesto de venta', 'purchase_date' => $date, 'supplier_name' => null,
            'tax' => 0, 'total' => $total / 100, 'items' => $items,
            'warnings' => ['Es un presupuesto; verifica la compra antes de registrarla. El cliente y el colaborador del documento no identifican al proveedor.']];
    }

    private function invalid(string $message): never
    {
        throw ValidationException::withMessages(['document' => $message]);
    }
}
