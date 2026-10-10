<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $t) {
            $t->unsignedBigInteger('client_id')->nullable()->change();
            $t->unsignedBigInteger('client_type_id')->nullable()->change();
            $t->date('date')->nullable();
            $t->string('status')->default('legacy')->index();
            $t->foreignId('warehouse_location_id')->nullable()->constrained('inventory_locations');
            $t->foreignId('created_by')->nullable()->constrained('users');
            $t->text('notes')->nullable();
            foreach (['subtotal', 'discount', 'tax', 'total'] as $field) {
                $t->decimal($field, 14, 2)->default(0);
            }
            $t->boolean('uses_operational_inventory')->default(false);
        });
        Schema::create('operational_sale_items', function (Blueprint $t) {
            $t->id();
            $t->foreignId('sale_id')->constrained('sales');
            $t->foreignId('product_id')->constrained('products');
            $t->string('product_name');
            $t->string('unit');
            $t->decimal('quantity', 14, 3);
            $t->decimal('unit_price', 14, 2);
            $t->decimal('subtotal', 14, 2);
            $t->timestamps();
            $t->unique(['sale_id', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('operational_sale_items');
        Schema::table('sales', function (Blueprint $t) {
            $t->dropConstrainedForeignId('warehouse_location_id');
            $t->dropConstrainedForeignId('created_by');
            $t->dropIndex(['status']);
            $t->dropColumn(['date', 'status', 'notes', 'subtotal', 'discount', 'tax', 'total', 'uses_operational_inventory']);
        });
        // Manual customer records may have null legacy IDs; keep them nullable.
    }
};
