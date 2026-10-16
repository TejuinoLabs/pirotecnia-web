<?php

namespace Tests\Feature\Events;

use App\Core\Data\Entities\Event as EventEntity;
use App\Core\UseCases\Events\GetEvent;
use App\Livewire\Panel\Events\EventForm;
use App\Models\Equipment;
use App\Models\Event;
use App\Models\EventType;
use App\Models\Inventory;
use App\Models\Package;
use App\Models\Product;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\TestCase;

class CreateEventSnapshotTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // The fixtures describe future events relative to this fixed capture date.
        Carbon::setTestNow('2026-04-25 12:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_it_creates_registered_events_with_snapshots_and_deferred_inventory_discount(): void
    {
        Queue::fake();

        $this->seedCatalog();

        $eventType = $this->createEventType('Boda');
        $inventory = $this->createInventory();
        $product = Product::create([
            'product_role_id' => 1,
            'name' => 'Volcan Plata',
            'description' => 'Efecto de piso',
            'unit' => 'pz',
            'duration' => '30s',
            'shots' => '1',
            'caliber' => '2',
            'shape' => 'Volcan',
        ]);
        $equipment = $this->createEquipment('Base de disparo');
        $package = Package::create([
            'name' => 'Paquete Premium',
            'description' => 'Paquete con show completo',
            'price' => 5000,
            'duration' => '15 min',
            'video_url' => null,
            'experience_level_id' => null,
        ]);

        $this->attachInventoryStock($inventory, $product, 50, 300);
        $package->materials()->attach($product->id, ['quantity' => 4, 'price' => 125.5]);
        $package->equipments()->attach($equipment->id, ['quantity' => 2]);

        $component = Livewire::test(EventForm::class)
            ->set('date', '2026-05-01')
            ->set('phone', '3121234567')
            ->set('client_name', 'Maria Lopez')
            ->set('client_address', 'Colima Centro')
            ->set('event_address', 'Salon Real')
            ->set('event_date', '2026-05-20')
            ->set('event_time', '21:00')
            ->set('event_type_id', $eventType->id)
            ->set('packageMode', 'registered')
            ->set('package_id', [$package->id])
            ->set('discountString', '10%')
            ->set('deposit', '1500')
            ->set('viatic', '300')
            ->set('notes', 'Montaje principal')
            ->call('save');

        $event = Event::query()->latest('id')->firstOrFail();
        $component->assertRedirect(route('events.show', $event->id));

        $this->assertDatabaseHas('events', [
            'id' => $event->id,
            'client_name' => 'Maria Lopez',
            'event_type_id' => $eventType->id,
            'price' => 0,
            'contract_description' => null,
        ]);

        $this->assertDatabaseHas('event_package', [
            'event_id' => $event->id,
            'package_id' => $package->id,
            'quantity' => 1,
            'price' => 5000,
        ]);

        $this->assertDatabaseHas('equipment_event', [
            'event_id' => $event->id,
            'equipment_id' => $equipment->id,
            'quantity' => 2,
            'check_almacen' => 0,
            'check_employee' => 0,
        ]);

        $this->assertDatabaseHas('productables', [
            'product_id' => $product->id,
            'productable_type' => Event::class,
            'productable_id' => $event->id,
            'quantity' => 4,
            'price' => 125.5,
            'check_almacen' => 0,
            'check_employee' => 0,
        ]);

        $this->assertDatabaseHas('productables', [
            'product_id' => $product->id,
            'productable_type' => Inventory::class,
            'productable_id' => $inventory->id,
            'quantity' => 50,
            'price' => 300,
        ]);

        $package->update(['price' => 9999]);
        $inventory->products()->updateExistingPivot($product->id, ['price' => 480]);

        $this->assertDatabaseHas('event_package', [
            'event_id' => $event->id,
            'package_id' => $package->id,
            'price' => 5000,
        ]);

        $this->assertDatabaseHas('productables', [
            'product_id' => $product->id,
            'productable_type' => Event::class,
            'productable_id' => $event->id,
            'price' => 125.5,
        ]);

        $snapshot = EventEntity::fromEvent($event->fresh(['packages', 'products', 'equipments']));

        $this->assertSame(5000.0, (float) $snapshot->packages->first()->price);
        $this->assertSame(125.5, (float) $snapshot->products->first()->price);

        Inventory::updateQuantityProducts($event->fresh(['products', 'equipments']));

        $this->assertDatabaseHas('productables', [
            'product_id' => $product->id,
            'productable_type' => Inventory::class,
            'productable_id' => $inventory->id,
            'quantity' => 46,
            'price' => 480,
        ]);

        $this->assertDatabaseHas('productables', [
            'product_id' => $product->id,
            'productable_type' => Event::class,
            'productable_id' => $event->id,
            'check_almacen' => 1,
        ]);

        $this->assertDatabaseHas('equipment_event', [
            'event_id' => $event->id,
            'equipment_id' => $equipment->id,
            'check_almacen' => 1,
        ]);
    }

    public function test_it_allows_using_the_same_registered_package_multiple_times(): void
    {
        Queue::fake();

        $this->seedCatalog();

        $eventType = $this->createEventType('Festival');
        $inventory = $this->createInventory();
        $product = Product::create([
            'product_role_id' => 1,
            'name' => 'Bateria Cometa',
            'description' => 'Disparo multiple',
            'unit' => 'pz',
            'duration' => '45s',
            'shots' => '16',
            'caliber' => '2',
            'shape' => 'Cake',
        ]);
        $equipment = $this->createEquipment('Modulo de disparo');
        $package = Package::create([
            'name' => 'Paquete Doble',
            'description' => 'Paquete reutilizable',
            'price' => 3200,
            'duration' => '8 min',
            'video_url' => null,
            'experience_level_id' => null,
        ]);

        $this->attachInventoryStock($inventory, $product, 80, 140);
        $package->materials()->attach($product->id, ['quantity' => 3, 'price' => 260]);
        $package->equipments()->attach($equipment->id, ['quantity' => 2]);

        $component = Livewire::test(EventForm::class)
            ->set('date', '2026-05-01')
            ->set('phone', '3120001122')
            ->set('client_name', 'Operadora Ferial')
            ->set('client_address', 'Centro ferial')
            ->set('event_address', 'Foro principal')
            ->set('event_date', '2026-05-25')
            ->set('event_time', '22:00')
            ->set('event_type_id', $eventType->id)
            ->set('packageMode', 'registered')
            ->set('package_id', [$package->id, $package->id])
            ->call('save');

        $event = Event::query()->latest('id')->firstOrFail();
        $component->assertRedirect(route('events.show', $event->id));

        $this->assertDatabaseHas('event_package', [
            'event_id' => $event->id,
            'package_id' => $package->id,
            'quantity' => 2,
            'price' => 3200,
        ]);

        $this->assertDatabaseHas('productables', [
            'product_id' => $product->id,
            'productable_type' => Event::class,
            'productable_id' => $event->id,
            'quantity' => 6,
            'price' => 260,
        ]);

        $this->assertDatabaseHas('equipment_event', [
            'event_id' => $event->id,
            'equipment_id' => $equipment->id,
            'quantity' => 4,
        ]);

        $contractEvent = app(GetEvent::class)->execute($event->id);

        $this->assertNotNull($contractEvent);
        $this->assertSame(2, (int) $contractEvent->packages->first()->quantity);
        $this->assertSame(3200.0, (float) $contractEvent->packages->first()->price);
    }

    public function test_it_creates_custom_events_with_snapshotted_products_and_reserved_inventory(): void
    {
        Queue::fake();

        $this->seedCatalog();

        $eventType = $this->createEventType('Cumpleanos');
        $inventory = $this->createInventory();
        $product = Product::create([
            'product_role_id' => 1,
            'name' => 'Bengala fria',
            'description' => 'Bengala decorativa',
            'unit' => 'pz',
            'duration' => '20s',
            'shots' => '1',
            'caliber' => '1',
            'shape' => 'Lineal',
        ]);
        $material = Product::create([
            'product_role_id' => 2,
            'name' => 'Confeti metalico',
            'description' => 'Carga de confeti',
            'unit' => 'kg',
            'duration' => null,
            'shots' => null,
            'caliber' => null,
            'shape' => 'Carga',
        ]);

        $this->attachInventoryStock($inventory, $product, 25, 180);
        $this->attachInventoryStock($inventory, $material, 20, 60);

        $component = Livewire::test(EventForm::class)
            ->set('date', '2026-06-01')
            ->set('phone', '3127654321')
            ->set('client_name', 'Juan Perez')
            ->set('client_address', 'Villa de Alvarez')
            ->set('event_address', 'Jardin Central')
            ->set('event_date', '2026-06-10')
            ->set('event_time', '19:00')
            ->set('event_type_id', $eventType->id)
            ->set('packageMode', 'custom')
            ->set('customProducts', [
                ['product_id' => $product->id, 'quantity' => 3, 'price' => 275],
                ['product_id' => $material->id, 'quantity' => 2, 'price' => 95],
            ])
            ->set('price', '4200')
            ->set('contract_description', 'Show personalizado con apertura brillante, bloque central sincronizado y cierre dorado.')
            ->set('deposit', '1000')
            ->set('viatic', '250')
            ->set('notes', 'Show personalizado')
            ->call('save');

        $event = Event::query()->latest('id')->firstOrFail();
        $component->assertRedirect(route('events.show', $event->id));

        $this->assertDatabaseHas('events', [
            'id' => $event->id,
            'client_name' => 'Juan Perez',
            'event_type_id' => $eventType->id,
            'price' => 4200,
            'package_id' => null,
            'contract_description' => 'Show personalizado con apertura brillante, bloque central sincronizado y cierre dorado.',
        ]);

        $this->assertDatabaseMissing('event_package', [
            'event_id' => $event->id,
        ]);

        $this->assertDatabaseMissing('equipment_event', [
            'event_id' => $event->id,
        ]);

        $this->assertDatabaseHas('productables', [
            'product_id' => $product->id,
            'productable_type' => Event::class,
            'productable_id' => $event->id,
            'quantity' => 3,
            'price' => 275,
            'check_almacen' => 0,
            'check_employee' => 0,
        ]);

        $this->assertDatabaseHas('productables', [
            'product_id' => $material->id,
            'productable_type' => Event::class,
            'productable_id' => $event->id,
            'quantity' => 2,
            'price' => 95,
            'check_almacen' => 0,
            'check_employee' => 0,
        ]);

        $this->assertDatabaseHas('productables', [
            'product_id' => $product->id,
            'productable_type' => Inventory::class,
            'productable_id' => $inventory->id,
            'quantity' => 25,
            'price' => 180,
        ]);

        $inventory->products()->updateExistingPivot($product->id, ['price' => 250]);
        $inventory->products()->updateExistingPivot($material->id, ['price' => 90]);

        $snapshot = EventEntity::fromEvent($event->fresh(['packages', 'products', 'equipments']));

        $this->assertTrue($snapshot->packages->isEmpty());
        $this->assertSame(
            'Show personalizado con apertura brillante, bloque central sincronizado y cierre dorado.',
            $snapshot->contract_description
        );
        $this->assertSame(275.0, (float) $snapshot->products->firstWhere('id', $product->id)->price);
        $this->assertSame(95.0, (float) $snapshot->products->firstWhere('id', $material->id)->price);

        $contractItems = $this->buildContractItemsForEvent($event);

        $this->assertCount(1, $contractItems);
        $this->assertSame(
            'Show personalizado con apertura brillante, bloque central sincronizado y cierre dorado.',
            $contractItems[0]['descripcion']
        );
        $this->assertSame('', $contractItems[0]['cantidad']);
        $this->assertSame(0, $contractItems[0]['precio']);

        Inventory::updateQuantityProducts($event->fresh(['products', 'equipments']));

        $this->assertDatabaseHas('productables', [
            'product_id' => $product->id,
            'productable_type' => Inventory::class,
            'productable_id' => $inventory->id,
            'quantity' => 22,
            'price' => 250,
        ]);

        $this->assertDatabaseHas('productables', [
            'product_id' => $material->id,
            'productable_type' => Inventory::class,
            'productable_id' => $inventory->id,
            'quantity' => 18,
            'price' => 90,
        ]);

        $this->assertDatabaseHas('productables', [
            'product_id' => $product->id,
            'productable_type' => Event::class,
            'productable_id' => $event->id,
            'check_almacen' => 1,
        ]);

        $this->assertDatabaseHas('productables', [
            'product_id' => $material->id,
            'productable_type' => Event::class,
            'productable_id' => $event->id,
            'check_almacen' => 1,
        ]);
    }

    public function test_it_creates_new_inventory_products_from_custom_events_and_reserves_them(): void
    {
        Queue::fake();

        $this->seedCatalog();

        $eventType = $this->createEventType('XV Anos');
        $inventory = $this->createInventory();

        $component = Livewire::test(EventForm::class)
            ->set('packageMode', 'custom')
            ->set('newCustomProductName', 'Volcan Titanio')
            ->set('newCustomProductDescription', 'Producto creado durante el alta del evento')
            ->set('newCustomProductUnit', 'pz')
            ->set('newCustomProductQuantity', 3)
            ->set('newCustomProductStock', 15)
            ->set('newCustomProductPrice', '210')
            ->set('newCustomProductEventPrice', '360')
            ->call('addNewCustomProduct')
            ->set('date', '2026-07-01')
            ->set('phone', '3121112233')
            ->set('client_name', 'Laura Rangel')
            ->set('client_address', 'Manzanillo')
            ->set('event_address', 'Terraza del Mar')
            ->set('event_date', '2026-07-15')
            ->set('event_time', '20:30')
            ->set('event_type_id', $eventType->id)
            ->set('price', '3800')
            ->set('deposit', '1200')
            ->set('viatic', '150')
            ->set('notes', 'Evento con producto creado en captura')
            ->call('save');

        $event = Event::query()->latest('id')->firstOrFail();
        $product = Product::query()->where('name', 'Volcan Titanio')->firstOrFail();

        $component->assertRedirect(route('events.show', $event->id));

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'product_role_id' => 1,
            'name' => 'Volcan Titanio',
            'description' => 'Producto creado durante el alta del evento',
            'unit' => 'pz',
        ]);

        $this->assertDatabaseHas('productables', [
            'product_id' => $product->id,
            'productable_type' => Inventory::class,
            'productable_id' => $inventory->id,
            'quantity' => 15,
            'price' => 210,
        ]);

        $this->assertDatabaseHas('productables', [
            'product_id' => $product->id,
            'productable_type' => Event::class,
            'productable_id' => $event->id,
            'quantity' => 3,
            'price' => 360,
            'check_almacen' => 0,
            'check_employee' => 0,
        ]);

        Inventory::updateQuantityProducts($event->fresh(['products', 'equipments']));

        $this->assertDatabaseHas('productables', [
            'product_id' => $product->id,
            'productable_type' => Inventory::class,
            'productable_id' => $inventory->id,
            'quantity' => 12,
            'price' => 210,
        ]);

        $this->assertDatabaseHas('productables', [
            'product_id' => $product->id,
            'productable_type' => Event::class,
            'productable_id' => $event->id,
            'check_almacen' => 1,
        ]);
    }

    public function test_it_requires_confirmation_before_saving_past_events(): void
    {
        Queue::fake();
        Carbon::setTestNow('2026-04-25 12:00:00');

        $this->seedCatalog();

        $eventType = $this->createEventType('Historico');
        $package = Package::create([
            'name' => 'Paquete Archivo',
            'description' => 'Registro historico',
            'price' => 1800,
            'duration' => '6 min',
            'video_url' => null,
            'experience_level_id' => null,
        ]);

        Livewire::test(EventForm::class)
            ->set('date', '2026-04-25')
            ->set('phone', '3129990011')
            ->set('client_name', 'Cliente Historico')
            ->set('client_address', 'Colonia Archivo')
            ->set('event_address', 'Archivo Central')
            ->set('event_date', '2026-04-20')
            ->set('event_time', '18:00')
            ->set('event_type_id', $eventType->id)
            ->set('packageMode', 'registered')
            ->set('package_id', [$package->id])
            ->call('save')
            ->assertSet('showPastDateModal', true);

        $this->assertDatabaseCount('events', 0);

        $component = Livewire::test(EventForm::class)
            ->set('date', '2026-04-25')
            ->set('phone', '3129990011')
            ->set('client_name', 'Cliente Historico')
            ->set('client_address', 'Colonia Archivo')
            ->set('event_address', 'Archivo Central')
            ->set('event_date', '2026-04-20')
            ->set('event_time', '18:00')
            ->set('event_type_id', $eventType->id)
            ->set('packageMode', 'registered')
            ->set('package_id', [$package->id])
            ->call('save')
            ->call('confirmPastDateAndSave');

        $event = Event::query()->latest('id')->firstOrFail();
        $component->assertRedirect(route('events.show', $event->id));

        Carbon::setTestNow();
    }

    public function test_it_hides_contract_breakdown_when_a_registered_event_uses_a_manual_price(): void
    {
        Queue::fake();

        $this->seedCatalog();

        $eventType = $this->createEventType('Corporativo');
        $package = Package::create([
            'name' => 'Paquete Ejecutivo',
            'description' => 'Show corporativo',
            'price' => 2800,
            'duration' => '5 min',
            'video_url' => null,
            'experience_level_id' => null,
        ]);

        $component = Livewire::test(EventForm::class)
            ->set('date', '2026-05-05')
            ->set('phone', '3124455667')
            ->set('client_name', 'Cliente Corporativo')
            ->set('client_address', 'Parque Industrial')
            ->set('event_address', 'Salones Ejecutivos')
            ->set('event_date', '2026-05-30')
            ->set('event_time', '20:00')
            ->set('event_type_id', $eventType->id)
            ->set('packageMode', 'registered')
            ->set('package_id', [$package->id])
            ->set('price', '6900')
            ->call('save');

        $event = Event::query()->latest('id')->firstOrFail();
        $component->assertRedirect(route('events.show', $event->id));

        $this->assertDatabaseHas('events', [
            'id' => $event->id,
            'price' => 6900,
        ]);

        $contractItems = $this->buildContractItemsForEvent($event);

        $this->assertCount(1, $contractItems);
        $this->assertSame('Paquete Ejecutivo', $contractItems[0]['descripcion']);
        $this->assertSame('', $contractItems[0]['cantidad']);
        $this->assertSame(0, $contractItems[0]['precio']);
    }

    public function test_it_marks_missing_event_fields_on_both_save_actions_without_writing_an_event(): void
    {
        foreach (['save', 'saveAndContinue'] as $action) {
            $component = Livewire::test(EventForm::class)
                ->set('date', '')
                ->set('event_type_id', '')
                ->set('packageMode', 'registered')
                ->call($action)
                ->assertHasErrors([
                    'date' => 'required',
                    'phone' => 'required',
                    'client_name' => 'required',
                    'event_address' => 'required',
                    'event_date' => 'required',
                    'event_time' => 'required',
                    'event_type_id' => 'required',
                    'package_id' => 'required',
                ])
                ->assertSee('Este campo es requerido.')
                ->assertSeeHtml('border-red-500')
                ->assertSeeHtml('aria-invalid="true"');

            $this->assertDatabaseCount('events', 0);
            $component->assertNotDispatched('openModal');
        }
    }

    public function test_new_custom_products_show_all_required_field_errors(): void
    {
        Livewire::test(EventForm::class)
            ->set('newCustomProductName', '   ')
            ->set('newCustomProductUnit', '')
            ->call('addNewCustomProduct')
            ->assertHasErrors([
                'newCustomProductName' => 'required',
                'newCustomProductUnit' => 'required',
            ])
            ->assertSet('customProducts', []);
    }

    public function test_custom_product_prices_are_optional_but_the_package_price_is_required(): void
    {
        Queue::fake();
        $this->seedCatalog();
        $eventType = $this->createEventType('Boda');
        $inventory = $this->createInventory();
        $existingProduct = Product::create([
            'product_role_id' => 1,
            'name' => 'Producto sin inventario',
            'description' => 'Producto existente',
            'unit' => 'pz',
        ]);

        $component = Livewire::test(EventForm::class)
            ->set('packageMode', 'custom')
            ->set('phone', '3121112233')
            ->set('client_name', 'Laura Rangel')
            ->set('event_address', 'Terraza del Mar')
            ->set('event_date', '2026-07-15')
            ->set('event_time', '20:30')
            ->set('event_type_id', $eventType->id)
            ->call('addCustomProduct', $existingProduct->id)
            ->set('newCustomProductName', 'Producto sin precio')
            ->call('addNewCustomProduct')
            ->assertHasNoErrors();

        foreach (['', '0', '-10'] as $price) {
            $component->set('price', $price)
                ->call('save')
                ->assertHasErrors('price')
                ->assertHasNoErrors('customProducts');
            $this->assertDatabaseCount('events', 0);
            $this->assertDatabaseMissing('products', ['name' => 'Producto sin precio']);
        }

        $component->set('price', '$3,800.00')->call('save')->assertHasNoErrors();
        $event = Event::query()->latest('id')->firstOrFail();
        $newProduct = Product::where('name', 'Producto sin precio')->firstOrFail();
        $component->assertRedirect(route('events.show', $event->id));
        $this->assertEquals(3800, $event->price);

        foreach ([$existingProduct, $newProduct] as $product) {
            $this->assertDatabaseHas('productables', [
                'product_id' => $product->id,
                'productable_type' => Event::class,
                'productable_id' => $event->id,
                'price' => 0,
            ]);
            $this->assertDatabaseHas('productables', [
                'product_id' => $product->id,
                'productable_type' => Inventory::class,
                'productable_id' => $inventory->id,
                'price' => 0,
            ]);
        }
    }

    private function seedCatalog(): void
    {
        DB::table('product_roles')->insert([
            ['id' => 1, 'name' => 'Productos', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 2, 'name' => 'Materiales', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 3, 'name' => 'Productos Paquete', 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    private function createEventType(string $name): EventType
    {
        $eventType = new EventType;
        $eventType->forceFill([
            'name' => $name,
            'description' => $name.' descripcion',
        ])->save();

        return $eventType;
    }

    private function createInventory(): Inventory
    {
        $inventory = new Inventory;
        $inventory->forceFill([
            'id' => 1,
            'name' => 'Polvorin Central',
            'location' => 'Bodega principal',
        ])->save();

        return $inventory;
    }

    private function createEquipment(string $name): Equipment
    {
        $equipment = new Equipment;
        $equipment->forceFill([
            'name' => $name,
            'description' => $name.' descripcion',
            'unit' => 'pz',
        ])->save();

        return $equipment;
    }

    private function attachInventoryStock(Inventory $inventory, Product $product, int $quantity, float $price): void
    {
        $inventory->products()->attach($product->id, [
            'quantity' => $quantity,
            'price' => $price,
        ]);
    }

    private function buildContractItemsForEvent(Event $event): array
    {
        $contractEvent = app(GetEvent::class)->execute($event->id);
        $controller = app(\App\Http\Controllers\Panel\EventController::class);
        $method = new \ReflectionMethod($controller, 'buildContractItems');
        $method->setAccessible(true);

        return $method->invoke($controller, $contractEvent, (float) ($contractEvent->price ?? 0));
    }
}
