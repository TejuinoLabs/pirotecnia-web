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
            $table->boolean('discount_enabled')->default(false)->after('status');
            $table->decimal('discount_rate', 5, 2)->default(0)->after('discount_enabled');
            $table->boolean('iva_enabled')->default(false)->after('discount_rate');
            $table->decimal('iva_rate', 5, 2)->default(16)->after('iva_enabled');
            $table->boolean('credit_limit_enabled')->default(false)->after('iva_rate');
            $table->decimal('credit_limit_amount', 12, 2)->nullable()->after('credit_limit_enabled');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('purchases', function (Blueprint $table) {
            $table->dropColumn([
                'discount_enabled',
                'discount_rate',
                'iva_enabled',
                'iva_rate',
                'credit_limit_enabled',
                'credit_limit_amount',
            ]);
        });
    }
};
