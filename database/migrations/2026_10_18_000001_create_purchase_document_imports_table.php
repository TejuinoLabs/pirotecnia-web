<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_document_imports', function (Blueprint $table) {
            $table->id();
            $table->uuid('token')->unique();
            $table->char('sha256', 64)->unique();
            $table->string('path');
            $table->json('extracted');
            $table->foreignId('purchase_id')->nullable()->constrained('purchases');
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_document_imports');
    }
};
