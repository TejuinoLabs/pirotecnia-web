<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['version' => 'unsignedInteger', 'content_hash' => 'string'] as $column => $type) {
            if (! Schema::hasColumn('event_dispatches', $column)) {
                Schema::table('event_dispatches', fn (Blueprint $t) => $t->$type($column)->nullable());
            }
        }
        foreach (['technician_receipt_items', 'mobile_return_declaration_items', 'event_return_items'] as $table) {
            if (! Schema::hasColumn($table, 'serial_number')) {
                Schema::table($table, fn (Blueprint $t) => $t->string('serial_number')->nullable());
            }
        }
        if (! Schema::hasTable('event_departure_passes')) {
            Schema::create('event_departure_passes', function (Blueprint $t) {
                $t->id();
                $t->foreignId('event_id')->constrained();
                $t->foreignId('technician_id')->constrained('users');
                $t->string('folio')->unique();
                $t->unsignedInteger('version');
                $t->json('dispatches');
                $t->foreignId('issued_by')->constrained('users');
                $t->timestamp('issued_at');
                $t->timestamp('expires_at');
                $t->foreignId('revoked_by')->nullable()->constrained('users');
                $t->timestamp('revoked_at')->nullable();
                $t->text('revocation_reason')->nullable();
                $t->timestamps();
                $t->unique(['event_id', 'technician_id', 'version'], 'departure_pass_version_unique');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('event_departure_passes');
        foreach (['technician_receipt_items', 'mobile_return_declaration_items', 'event_return_items'] as $table) {
            Schema::table($table, fn (Blueprint $t) => $t->dropColumn('serial_number'));
        }
        Schema::table('event_dispatches', fn (Blueprint $t) => $t->dropColumn(['version', 'content_hash']));
    }
};
