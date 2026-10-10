<?php

namespace Tests\Unit\Inventory;

use App\Models\Product;
use App\Services\Inventory\ProductMatcherService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ProductMatcherServiceTest extends TestCase
{
    public static function operators(): array
    {
        return [
            [100, '=', 100, true], ['100', '=', 100, true], [99, '=', 100, false],
            [100, '!=', 100, false], [100, '>', 99, true], [100, '>=', 100, true],
            [100, '<', 101, true], [100, '<=', 99, false], [100, 'between', [90, 110], true],
            [100, 'in', [99, 100], true], [100, 'not_in', [99, 100], false],
            [[1, 2], 'contains', 2, true], ['blue', 'contains', 'lu', true],
            [null, '!=', 100, false], ['100 tiros', '>=', 100, false],
        ];
    }

    #[DataProvider('operators')]
    public function test_it_matches_typed_constraints(mixed $actual, string $operator, mixed $value, bool $expected): void
    {
        $matcher = new class($actual) extends ProductMatcherService
        {
            public function __construct(private mixed $actual) {}

            public function attribute(Product $product, string $code): mixed
            {
                return $this->actual;
            }
        };
        $this->assertSame($expected, $matcher->constraintMatch(new Product, ['attribute_code' => 'test', 'operator' => $operator, 'value' => $value]));
    }
}
