<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('inventory_locations', 'inventory_area')) {
            Schema::table('inventory_locations', fn (Blueprint $t) => $t->string('inventory_area')->default('polvorin')->index());
            DB::table('inventory_locations')->where('type', 'stand')->update(['inventory_area' => 'stand']);
            DB::table('inventory_locations')->whereNotIn('type', ['warehouse', 'stand'])->update(['inventory_area' => 'external']);
        }
        if (! Schema::hasColumn('purchases', 'destination_location_id')) {
            Schema::table('purchases', fn (Blueprint $t) => $t->foreignId('destination_location_id')->nullable()->constrained('inventory_locations'));
        }
    }

    public function down(): void
    {
        Schema::table('purchases', fn (Blueprint $t) => $t->dropConstrainedForeignId('destination_location_id'));
        Schema::table('inventory_locations', function (Blueprint $t) {
            $t->dropIndex(['inventory_area']);
            $t->dropColumn('inventory_area');
        });
    }
};
