<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('purchases', function (Blueprint $table) {
            $table->string('supplier_name')->nullable()->after('date');
            $table->string('payment_method')->nullable()->after('supplier_name');
            $table->string('reference')->nullable()->after('payment_method');
            $table->text('observations')->nullable()->after('reference');
            $table->string('status')->default('confirmed')->after('observations');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('purchases', function (Blueprint $table) {
            $table->dropColumn([
                'supplier_name',
                'payment_method',
                'reference',
                'observations',
                'status',
            ]);
        });
    }
};
