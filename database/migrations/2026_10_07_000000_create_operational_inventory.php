<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Persist this before MySQL DDL, which can survive a failed migration.
        if (! Schema::hasTable('operational_inventory_migration_state')) {
            $adopted = Schema::hasTable('categories') || Schema::hasTable('inventory_locations') || Schema::hasColumn('products', 'active');
            Schema::create('operational_inventory_migration_state', function (Blueprint $t) {
                $t->id();
                $t->boolean('adopted_existing_schema');
            });
            DB::table('operational_inventory_migration_state')->insert(['adopted_existing_schema' => $adopted]);
        }
        foreach (['categories', 'effects', 'colors'] as $name) {
            $this->createTable($name, function (Blueprint $t) {
                $t->id();
                $t->string('name');
                $t->string('code')->unique();
                $t->boolean('active')->default(true);
                $t->timestamps();
            });
        }
        $this->createTable('attribute_definitions', function (Blueprint $t) {
            $t->id();
            $t->string('code')->unique();
            $t->string('name');
            $t->string('data_type');
            $t->string('unit')->nullable();
            $t->boolean('active')->default(true);
            $t->timestamps();
        });
        Schema::table('products', function (Blueprint $t) {
            if (! Schema::hasColumn('products', 'legacy_equipment_id')) {
                $t->foreignId('legacy_equipment_id')->nullable()->unique()->constrained('equipments');
            }
            if (! Schema::hasColumn('products', 'sku')) {
                $t->string('sku')->nullable()->unique();
            }
            if (! Schema::hasColumn('products', 'category_id')) {
                $t->foreignId('category_id')->nullable()->constrained('categories');
            }
            if (! Schema::hasColumn('products', 'active')) {
                $t->boolean('active')->default(true);
            }
            if (! Schema::hasColumn('products', 'tracks_inventory')) {
                $t->boolean('tracks_inventory')->default(true);
            }
            if (! Schema::hasColumn('products', 'is_consumable')) {
                $t->boolean('is_consumable')->default(true);
            }
            if (! Schema::hasColumn('products', 'is_returnable')) {
                $t->boolean('is_returnable')->default(false);
            }
            if (! Schema::hasColumn('products', 'is_sellable')) {
                $t->boolean('is_sellable')->default(true);
            }
            if (! Schema::hasColumn('products', 'is_event_usable')) {
                $t->boolean('is_event_usable')->default(true);
            }
            foreach (['reference_value', 'purchase_cost', 'sale_price'] as $field) {
                if (! Schema::hasColumn('products', $field)) {
                    $t->decimal($field, 14, 2)->nullable();
                }
            }
            if (! Schema::hasColumn('products', 'minimum_stock')) {
                $t->decimal('minimum_stock', 14, 3)->default(0);
            }
        });
        $this->createTable('product_attribute_values', function (Blueprint $t) {
            $t->id();
            $t->foreignId('product_id')->constrained();
            $t->foreignId('attribute_definition_id')->constrained();
            $t->text('value_string')->nullable();
            $t->decimal('value_number', 16, 4)->nullable();
            $t->boolean('value_boolean')->nullable();
            $t->json('value_json')->nullable();
            $t->timestamps();
            $t->unique(['product_id', 'attribute_definition_id'], 'product_id_unique_index');
        });
        foreach (['effects' => 'effect_id', 'colors' => 'color_id'] as $name => $field) {
            $this->createTable('product_'.$name, function (Blueprint $t) use ($name, $field) {
                $t->foreignId('product_id')->constrained();
                $t->foreignId($field)->constrained($name);
                $t->primary(['product_id', $field]);
            });
        }
        $this->createTable('inventory_locations', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('code')->unique();
            $t->string('type');
            $t->foreignId('parent_id')->nullable()->constrained('inventory_locations');
            $t->foreignId('legacy_inventory_id')->nullable()->unique()->constrained('inventories');
            $t->boolean('active')->default(true);
            $t->timestamp('adopted_at')->nullable();
            $t->timestamps();
        });
        $this->createTable('inventory_balances', function (Blueprint $t) {
            $t->id();
            $t->foreignId('product_id')->constrained();
            $t->foreignId('location_id')->constrained('inventory_locations');
            $t->decimal('quantity', 14, 3)->default(0);
            $t->timestamps();
            $t->unique(['product_id', 'location_id']);
        });
        $this->createTable('inventory_movements', function (Blueprint $t) {
            $t->id();
            $t->foreignId('product_id')->constrained();
            $t->string('movement_type')->index();
            $t->decimal('quantity', 14, 3);
            $t->foreignId('from_location_id')->nullable()->constrained('inventory_locations');
            $t->foreignId('to_location_id')->nullable()->constrained('inventory_locations');
            $t->string('source_type')->nullable();
            $t->unsignedBigInteger('source_id')->nullable();
            $t->string('reference')->nullable();
            $t->timestamp('occurred_at')->index();
            $t->timestamp('recorded_at');
            $t->foreignId('created_by')->constrained('users');
            $t->text('notes');
            $t->json('metadata')->nullable();
            $t->timestamps();
            $t->index(['source_type', 'source_id']);
        });
        $this->createTable('inventory_audits', function (Blueprint $t) {
            $t->id();
            $t->foreignId('actor_id')->constrained('users');
            $t->string('action');
            $t->string('entity_type');
            $t->unsignedBigInteger('entity_id');
            $t->json('old_values')->nullable();
            $t->json('new_values')->nullable();
            $t->string('ip')->nullable();
            $t->text('user_agent')->nullable();
            $t->timestamp('created_at');
        });
        $this->createTable('inventory_permissions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained();
            $t->string('permission');
            $t->unique(['user_id', 'permission']);
        });
        $this->createTable('inventory_idempotency_keys', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained();
            $t->string('key', 100);
            $t->string('operation');
            $t->string('request_hash', 64);
            $t->json('response')->nullable();
            $t->timestamps();
            $t->unique(['user_id', 'key', 'operation'], 'inventory_idempotency_unique');
        });
        Schema::table('purchases', function (Blueprint $t) {
            if (! Schema::hasColumn('purchases', 'expected_date')) {
                $t->date('expected_date')->nullable();
            }
            if (! Schema::hasColumn('purchases', 'subtotal')) {
                $t->decimal('subtotal', 14, 2)->default(0);
            }
            if (! Schema::hasColumn('purchases', 'tax')) {
                $t->decimal('tax', 14, 2)->default(0);
            }
            if (! Schema::hasColumn('purchases', 'total')) {
                $t->decimal('total', 14, 2)->default(0);
            }
            if (! Schema::hasColumn('purchases', 'uses_operational_inventory')) {
                $t->boolean('uses_operational_inventory')->default(false);
            }
        });
        // The existing purchase_items requires presentations and uses "quantiy".
        $this->createTable('operational_purchase_items', function (Blueprint $t) {
            $t->id();
            $t->foreignId('purchase_id')->constrained();
            $t->foreignId('product_id')->constrained();
            $t->decimal('quantity', 14, 3);
            $t->decimal('unit_cost', 14, 2);
            $t->decimal('received_quantity', 14, 3)->default(0);
            $t->timestamps();
        });
        $this->createTable('purchase_receipts', function (Blueprint $t) {
            $t->id();
            $t->foreignId('purchase_id')->constrained();
            $t->foreignId('warehouse_location_id')->constrained('inventory_locations');
            $t->timestamp('received_at');
            $t->foreignId('received_by')->constrained('users');
            $t->text('notes')->nullable();
            $t->timestamps();
        });
        $this->createTable('purchase_receipt_items', function (Blueprint $t) {
            $t->id();
            $t->foreignId('purchase_receipt_id')->constrained();
            $t->foreignId('purchase_item_id')->constrained('operational_purchase_items');
            $t->foreignId('product_id')->constrained();
            $t->decimal('quantity', 14, 3);
            $t->decimal('unit_cost', 14, 2);
            $t->timestamps();
        });
        $this->createTable('product_profiles', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->text('description')->nullable();
            $t->foreignId('category_id')->constrained('categories');
            $t->boolean('active')->default(true);
            $t->timestamps();
        });
        $this->createTable('package_requirements', function (Blueprint $t) {
            $t->id();
            $t->foreignId('package_id')->constrained();
            $this->requirementFields($t);
            $t->decimal('quantity', 14, 3);
            $t->integer('sort_order')->default(0);
            $t->timestamps();
        });
        if (! Schema::hasColumn('events', 'logistics_status')) {
            Schema::table('events', fn (Blueprint $t) => $t->string('logistics_status')->default('pending')->index());
        }
        $this->createTable('event_requirements', function (Blueprint $t) {
            $t->id();
            $t->foreignId('event_id')->constrained();
            $t->foreignId('source_package_requirement_id')->nullable()->constrained('package_requirements');
            $this->requirementFields($t);
            $t->decimal('quantity_required', 14, 3);
            $t->decimal('quantity_allocated', 14, 3)->default(0);
            $t->string('status')->default('pending');
            $t->json('snapshot');
            $t->timestamps();
        });
        $this->createTable('requirement_constraints', function (Blueprint $t) {
            $t->id();
            $t->string('owner_type');
            $t->unsignedBigInteger('owner_id');
            $t->string('attribute_code');
            $t->string('operator');
            $t->json('value');
            $t->boolean('is_hard_constraint')->default(true);
            $t->decimal('weight', 8, 2)->nullable();
            $t->timestamps();
            $t->index(['owner_type', 'owner_id']);
        });
        $this->createTable('inventory_reservations', function (Blueprint $t) {
            $t->id();
            $t->string('reservation_type');
            $t->foreignId('product_id')->nullable()->constrained();
            $t->foreignId('event_id')->constrained();
            $t->foreignId('event_requirement_id')->constrained();
            $t->decimal('quantity', 14, 3);
            $t->string('status')->default('active');
            $t->timestamps();
        });
        $this->createTable('inventory_protection_rules', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->boolean('active')->default(true);
            $t->integer('priority')->default(0);
            $t->foreignId('category_id')->nullable()->constrained('categories');
            $t->foreignId('product_id')->nullable()->constrained();
            $t->json('attribute_filters');
            $t->decimal('protected_quantity', 14, 3)->nullable();
            $t->decimal('protected_percentage', 6, 2)->nullable();
            $t->json('applicable_event_types')->nullable();
            $t->json('excluded_event_types')->nullable();
            $t->string('action');
            $t->text('message')->nullable();
            $t->timestamps();
        });
        $this->createTable('allocation_rules', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->text('description')->nullable();
            $t->boolean('active')->default(true);
            $t->integer('priority')->default(0);
            $t->string('scope');
            $t->json('condition_json');
            $t->json('action_json');
            $t->timestamps();
        });
        $this->createTable('requirement_allocations', function (Blueprint $t) {
            $t->id();
            $t->foreignId('event_requirement_id')->constrained();
            $t->foreignId('product_id')->constrained();
            $t->decimal('quantity', 14, 3);
            $t->foreignId('warehouse_location_id')->constrained('inventory_locations');
            $t->string('status')->default('reserved');
            $t->foreignId('allocated_by')->constrained('users');
            $t->timestamp('allocated_at');
            $t->text('notes')->nullable();
            $t->timestamps();
        });
        $this->createTable('allocation_rule_overrides', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained();
            $t->foreignId('rule_id')->nullable()->constrained('inventory_protection_rules');
            $t->foreignId('product_id')->constrained();
            $t->foreignId('event_id')->constrained();
            $t->text('reason');
            $t->timestamp('created_at');
        });
        $this->createTable('allocation_replacements', function (Blueprint $t) {
            $t->id();
            $t->foreignId('requirement_allocation_id')->constrained();
            $t->foreignId('old_product_id')->constrained('products');
            $t->foreignId('new_product_id')->constrained('products');
            $t->decimal('quantity', 14, 3);
            $t->text('reason');
            $t->foreignId('created_by')->constrained('users');
            $t->timestamp('created_at');
        });
        $this->createTable('inventory_alerts', function (Blueprint $t) {
            $t->id();
            $t->foreignId('event_id')->constrained();
            $t->foreignId('event_requirement_id')->constrained();
            $t->string('severity');
            $t->decimal('deficit', 14, 3);
            $t->json('details');
            $t->timestamps();
            $t->unique('event_requirement_id');
        });
        $this->createTable('event_preparations', function (Blueprint $t) {
            $t->id();
            $t->foreignId('event_id')->unique()->constrained();
            $t->string('status')->default('pending');
            $t->foreignId('responsible_id')->nullable()->constrained('users');
            $t->timestamp('started_at')->nullable();
            $t->timestamp('completed_at')->nullable();
            $t->timestamps();
        });
        $this->createTable('event_pickings', function (Blueprint $t) {
            $t->id();
            $t->foreignId('event_id')->constrained();
            $t->foreignId('warehouse_location_id')->constrained('inventory_locations');
            $t->string('status')->default('in_progress');
            $t->foreignId('started_by')->constrained('users');
            $t->timestamp('started_at');
            $t->foreignId('completed_by')->nullable()->constrained('users');
            $t->timestamp('completed_at')->nullable();
            $t->timestamps();
        });
        $this->createTable('event_picking_items', function (Blueprint $t) {
            $t->id();
            $t->foreignId('event_picking_id')->constrained();
            $t->foreignId('event_requirement_id')->constrained();
            $t->foreignId('requirement_allocation_id')->unique()->constrained();
            $t->foreignId('product_id')->constrained();
            $t->decimal('quantity_required', 14, 3);
            $t->decimal('quantity_picked', 14, 3)->default(0);
            $t->string('status')->default('pending');
            $t->text('notes')->nullable();
            $t->timestamps();
        });
        $this->createTable('event_dispatches', function (Blueprint $t) {
            $t->id();
            $t->foreignId('event_id')->constrained();
            $t->foreignId('picking_id')->unique()->constrained('event_pickings');
            $t->foreignId('warehouse_location_id')->constrained('inventory_locations');
            $t->foreignId('technician_id')->constrained('users');
            $t->foreignId('destination_location_id')->constrained('inventory_locations');
            $t->string('status')->default('draft');
            $t->timestamp('dispatched_at')->nullable();
            $t->foreignId('dispatched_by')->nullable()->constrained('users');
            $t->timestamp('received_at')->nullable();
            $t->foreignId('received_by')->nullable()->constrained('users');
            $t->text('notes')->nullable();
            $t->timestamps();
        });
        $this->createTable('event_dispatch_items', function (Blueprint $t) {
            $t->id();
            $t->foreignId('event_dispatch_id')->constrained();
            $t->foreignId('product_id')->constrained();
            $t->foreignId('event_requirement_id')->constrained();
            $t->foreignId('requirement_allocation_id')->constrained();
            $t->decimal('quantity', 14, 3);
            $t->boolean('is_returnable');
            $t->string('serial_number')->nullable();
            $t->timestamps();
        });
        $this->createTable('technician_receipts', function (Blueprint $t) {
            $t->id();
            $t->foreignId('event_dispatch_id')->unique()->constrained();
            $t->foreignId('technician_id')->constrained('users');
            $t->string('status');
            $t->timestamp('received_at');
            $t->text('notes')->nullable();
            $t->text('device_info')->nullable();
            $t->timestamps();
        });
        $this->createTable('technician_receipt_items', function (Blueprint $t) {
            $t->id();
            $t->foreignId('technician_receipt_id')->constrained();
            $t->foreignId('dispatch_item_id')->constrained('event_dispatch_items');
            $t->decimal('expected_quantity', 14, 3);
            $t->decimal('received_quantity', 14, 3);
            $t->string('status');
            $t->text('notes')->nullable();
            $t->timestamps();
        });
        $this->createTable('event_returns', function (Blueprint $t) {
            $t->id();
            $t->foreignId('event_id')->constrained();
            $t->foreignId('event_dispatch_id')->unique()->constrained();
            $t->foreignId('warehouse_location_id')->constrained('inventory_locations');
            $t->foreignId('damaged_location_id')->nullable()->constrained('inventory_locations');
            $t->foreignId('returned_by')->constrained('users');
            $t->foreignId('received_by')->nullable()->constrained('users');
            $t->timestamp('returned_at')->nullable();
            $t->string('status')->default('draft');
            $t->text('notes')->nullable();
            $t->timestamps();
        });
        $this->createTable('event_return_items', function (Blueprint $t) {
            $t->id();
            $t->foreignId('event_return_id')->constrained();
            $t->foreignId('dispatch_item_id')->constrained('event_dispatch_items');
            $t->foreignId('product_id')->constrained();
            foreach (['dispatched', 'consumed', 'returned', 'damaged', 'lost'] as $field) {
                $t->decimal('quantity_'.$field, 14, 3)->default(0);
            }
            $t->text('notes')->nullable();
            $t->timestamps();
        });
    }

    private function createTable(string $table, Closure $definition): void
    {
        if (! Schema::hasTable($table)) {
            Schema::create($table, $definition);

            return;
        }

        $blueprint = new Blueprint(DB::connection(), $table, $definition);
        $expected = array_map(fn ($column) => $column->name, $blueprint->getColumns());
        $missing = array_diff($expected, Schema::getColumnListing($table));
        if ($missing) {
            throw new RuntimeException('La tabla '.$table.' ya existe con un esquema incompatible. Faltan columnas: '.implode(', ', $missing));
        }
    }

    private function requirementFields(Blueprint $t): void
    {
        $t->string('requirement_type');
        $t->foreignId('product_id')->nullable()->constrained();
        $t->foreignId('category_id')->nullable()->constrained('categories');
        $t->foreignId('profile_id')->nullable()->constrained('product_profiles');
        $t->decimal('reference_value', 14, 2)->nullable();
        $t->decimal('value_tolerance', 14, 2)->nullable();
        $t->string('selection_strategy')->nullable();
        $t->boolean('allow_substitution')->default(true);
        $t->json('metadata')->nullable();
    }

    public function down(): void
    {
        if (Schema::hasTable('operational_inventory_migration_state') && DB::table('operational_inventory_migration_state')->value('adopted_existing_schema')) {
            throw new RuntimeException('Esta migración incorporó tablas existentes. No se pueden eliminar automáticamente al revertirla.');
        }
        foreach (['event_return_items', 'event_returns', 'technician_receipt_items', 'technician_receipts', 'event_dispatch_items', 'event_dispatches', 'event_picking_items', 'event_pickings', 'event_preparations', 'inventory_alerts', 'allocation_replacements', 'allocation_rule_overrides', 'requirement_allocations', 'allocation_rules', 'inventory_protection_rules', 'inventory_reservations', 'requirement_constraints', 'event_requirements', 'package_requirements', 'product_profiles', 'purchase_receipt_items', 'purchase_receipts', 'operational_purchase_items', 'inventory_idempotency_keys', 'inventory_permissions', 'inventory_audits', 'inventory_movements', 'inventory_balances', 'inventory_locations', 'product_effects', 'product_colors', 'product_attribute_values'] as $table) {
            Schema::dropIfExists($table);
        }
        Schema::table('events', function (Blueprint $t) {
            $t->dropIndex(['logistics_status']);
            $t->dropColumn('logistics_status');
        });
        Schema::table('purchases', fn (Blueprint $t) => $t->dropColumn(['expected_date', 'subtotal', 'tax', 'total', 'uses_operational_inventory']));
        Schema::table('products', function (Blueprint $t) {
            $t->dropUnique(['sku']);
            $t->dropUnique(['legacy_equipment_id']);
            $t->dropConstrainedForeignId('category_id');
            $t->dropConstrainedForeignId('legacy_equipment_id');
            $t->dropColumn(['sku', 'active', 'tracks_inventory', 'is_consumable', 'is_returnable', 'is_sellable', 'is_event_usable', 'reference_value', 'purchase_cost', 'sale_price', 'minimum_stock']);
        });
        foreach (['attribute_definitions', 'colors', 'effects', 'categories'] as $table) {
            Schema::dropIfExists($table);
        }
        Schema::dropIfExists('operational_inventory_migration_state');
    }
};
