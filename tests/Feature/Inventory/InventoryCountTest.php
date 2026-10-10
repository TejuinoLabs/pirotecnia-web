<?php

namespace Tests\Feature\Inventory;

use App\Models\User;
use App\Services\Inventory\InventoryData;
use App\Services\Inventory\ProductCatalogService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class InventoryCountTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private int $product;

    private int $location;

    protected function setUp(): void
    {
        parent::setUp();
        DB::table('roles')->insert(['id' => 1, 'name' => 'admin', 'description' => 'Admin']);
        DB::table('product_roles')->insert(['id' => 1, 'name' => 'Producto']);
        $this->admin = User::factory()->create(['role_id' => 1, 'phone' => '3121234567']);
        $this->product = app(ProductCatalogService::class)->save(['name' => 'Bomba', 'caliber' => '3', 'product_role_id' => 1, 'unit' => 'pz'], $this->admin->id)['id'];
        $this->location = InventoryData::insert('inventory_locations', ['name' => 'Almacén', 'code' => 'MAIN', 'type' => 'warehouse']);
        $this->actingAs($this->admin, 'api');
    }

    private function payload(float $quantity, float $expected = 0, string $purpose = 'initial'): array
    {
        return ['product_id' => $this->product, 'location_id' => $this->location, 'quantity' => $quantity, 'expected_quantity' => $expected, 'purpose' => $purpose, 'notes' => 'Conteo físico verificado'];
    }

    public function test_initial_count_and_correction_only_record_the_difference_without_a_purchase(): void
    {
        $this->getJson('/api/inventory/balances/'.$this->product.'?location_id='.$this->location)->assertOk()->assertJsonPath('quantity', 0);
        $this->postJson('/api/inventory/counts', $this->payload(12), ['Idempotency-Key' => 'opening-count'])->assertOk()->assertJsonPath('difference', 12);
        $this->postJson('/api/inventory/counts', $this->payload(12), ['Idempotency-Key' => 'opening-count'])->assertOk();
        $this->assertDatabaseCount('inventory_movements', 1);
        $this->postJson('/api/inventory/counts', $this->payload(7, 12, 'correction'))->assertOk()->assertJsonPath('difference', -5);
        $this->assertDatabaseHas('inventory_movements', ['movement_type' => 'adjustment_out', 'quantity' => 5, 'source_type' => 'inventory_count']);
        $this->postJson('/api/inventory/counts', $this->payload(0, 7, 'correction'))->assertOk();
        $this->getJson('/api/inventory/balances/'.$this->product.'?location_id='.$this->location)->assertOk()->assertJsonPath('quantity', 0);
        $this->postJson('/api/inventory/counts', $this->payload(0, 0, 'correction'))->assertOk()->assertJsonPath('movement_id', null);
        $this->assertDatabaseCount('inventory_movements', 3);
        $this->assertDatabaseCount('purchases', 0);
        $this->assertSame(4, DB::table('inventory_audits')->where('action', 'inventory_count')->count());
    }

    public function test_stale_counts_initial_count_reuse_and_invalid_precision_cannot_overwrite_stock(): void
    {
        $this->postJson('/api/inventory/counts', $this->payload(12))->assertOk();
        $this->postJson('/api/inventory/counts', $this->payload(20, 0, 'correction'))->assertConflict()->assertJsonPath('code', 'INVENTORY_COUNT_CONFLICT');
        $this->postJson('/api/inventory/counts', $this->payload(20, 12, 'initial'))->assertUnprocessable();
        $this->postJson('/api/inventory/counts', $this->payload(-1, 12, 'correction'))->assertUnprocessable();
        $this->postJson('/api/inventory/counts', $this->payload(1.1234, 12, 'correction'))->assertUnprocessable();
        $this->assertDatabaseHas('inventory_balances', ['product_id' => $this->product, 'location_id' => $this->location, 'quantity' => 12]);
        $this->assertDatabaseCount('inventory_movements', 1);
        DB::table('roles')->insert(['id' => 2, 'name' => 'technician', 'description' => 'Técnico']);
        $user = User::factory()->create(['role_id' => 2, 'phone' => '3120000000']);
        $this->actingAs($user, 'api')->postJson('/api/inventory/counts', $this->payload(20, 12, 'correction'))->assertForbidden();
    }
}
