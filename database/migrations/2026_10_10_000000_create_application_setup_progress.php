<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('application_setup_progress')) {
            Schema::create('application_setup_progress', function (Blueprint $table) {
                $table->foreignId('user_id')->primary()->constrained()->cascadeOnDelete();
                $table->unsignedTinyInteger('step')->default(1);
                $table->string('inventory_mode')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('application_setup_progress');
    }
};
