<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('event_dispatches', 'mobile_return_revision')) {
            Schema::table('event_dispatches', fn (Blueprint $t) => $t->unsignedInteger('mobile_return_revision')->default(1));
        }
        $this->createTable('mobile_event_protocols', function (Blueprint $t) {
            $t->id();
            $t->foreignId('event_id')->unique()->constrained();
            $t->unsignedInteger('version');
            $t->string('title');
            $t->string('status')->default('in_progress');
            $t->json('package_snapshot');
            $t->timestamp('closed_at')->nullable();
            $t->foreignId('closed_by')->nullable()->constrained('users');
            $t->timestamps();
        });
        $this->createTable('mobile_protocol_steps', function (Blueprint $t) {
            $t->id();
            $t->foreignId('protocol_id')->constrained('mobile_event_protocols');
            $t->unsignedInteger('sort_order');
            $t->string('title');
            $t->text('instructions');
            $t->boolean('required')->default(true);
            $t->string('capture_mode')->default('automatic');
            $t->json('allowed_capture_modes');
            $t->boolean('allow_notes')->default(true);
            $t->json('allowed_evidence');
            $t->json('dependencies');
            $t->foreignId('responsible_user_id')->nullable()->constrained('users');
            $t->timestamps();
            $t->unique(['protocol_id', 'sort_order']);
        });
        $this->createTable('mobile_step_records', function (Blueprint $t) {
            $t->id();
            $t->foreignId('event_id')->constrained();
            $t->foreignId('step_id')->constrained('mobile_protocol_steps');
            $t->unsignedInteger('protocol_version');
            $t->uuid('client_operation_id');
            $t->timestamp('occurred_at');
            $t->timestamp('recorded_at');
            $t->string('capture_mode');
            $t->text('manual_reason')->nullable();
            $t->text('notes')->nullable();
            $t->json('attachment_ids');
            $t->foreignId('recorded_by')->constrained('users');
            $t->unsignedInteger('revision')->default(1);
            $t->string('status')->default('accepted');
            $t->json('original_payload');
            $t->foreignId('corrected_by')->nullable()->constrained('users');
            $t->timestamp('corrected_at')->nullable();
            $t->timestamps();
            $t->unique('step_id');
            $t->unique(['recorded_by', 'client_operation_id']);
        });
        $this->createTable('mobile_record_corrections', function (Blueprint $t) {
            $t->id();
            $t->foreignId('record_id')->constrained('mobile_step_records');
            $t->foreignId('actor_id')->constrained('users');
            $t->uuid('client_operation_id');
            $t->unsignedInteger('revision');
            $t->text('reason');
            $t->json('before');
            $t->json('after');
            $t->timestamp('occurred_at');
            $t->timestamps();
            $t->unique(['record_id', 'revision']);
        });
        $this->createTable('mobile_step_overrides', function (Blueprint $t) {
            $t->id();
            $t->foreignId('event_id')->constrained();
            $t->foreignId('step_id')->unique()->constrained('mobile_protocol_steps');
            $t->unsignedInteger('protocol_version');
            $t->foreignId('actor_id')->constrained('users');
            $t->uuid('client_operation_id');
            $t->text('reason');
            $t->timestamps();
        });
        $this->createTable('mobile_incidents', function (Blueprint $t) {
            $t->id();
            $t->foreignId('event_id')->constrained();
            $t->foreignId('dispatch_item_id')->nullable()->constrained('event_dispatch_items');
            $t->foreignId('step_id')->nullable()->constrained('mobile_protocol_steps');
            $t->foreignId('receipt_id')->nullable()->constrained('technician_receipts');
            $t->uuid('client_operation_id')->nullable();
            $t->string('category');
            $t->string('severity');
            $t->text('description');
            $t->string('status')->default('open');
            $t->foreignId('created_by')->constrained('users');
            $t->timestamp('occurred_at');
            $t->timestamp('recorded_at');
            $t->json('attachment_ids');
            $t->timestamps();
            $t->index(['event_id', 'status']);
            $t->unique(['created_by', 'client_operation_id']);
        });
        $this->createTable('mobile_return_declarations', function (Blueprint $t) {
            $t->id();
            $t->foreignId('event_id')->constrained();
            $t->foreignId('dispatch_id')->constrained('event_dispatches');
            $t->foreignId('created_by')->constrained('users');
            $t->uuid('client_operation_id');
            $t->unsignedInteger('revision');
            $t->string('status')->default('declared');
            $t->timestamp('occurred_at');
            $t->timestamp('recorded_at');
            $t->text('notes')->nullable();
            $t->timestamp('warehouse_confirmed_at')->nullable();
            $t->foreignId('warehouse_return_id')->nullable()->constrained('event_returns');
            $t->timestamps();
            $t->unique(['dispatch_id', 'revision']);
            $t->unique(['created_by', 'client_operation_id']);
        });
        $this->createTable('mobile_return_declaration_items', function (Blueprint $t) {
            $t->id();
            $t->foreignId('declaration_id')->constrained('mobile_return_declarations');
            $t->foreignId('dispatch_item_id')->constrained('event_dispatch_items');
            foreach (['consumed', 'returned', 'damaged', 'lost', 'pending'] as $name) {
                $t->decimal('quantity_'.$name, 14, 3);
            }
            $t->text('notes')->nullable();
            $t->timestamps();
            $t->unique(['declaration_id', 'dispatch_item_id'], 'mobile_declaration_item_unique');
        });
        $this->createTable('mobile_attachments', function (Blueprint $t) {
            $t->id();
            $t->foreignId('event_id')->constrained();
            $t->foreignId('created_by')->constrained('users');
            $t->uuid('client_operation_id');
            $t->string('entity_type');
            $t->unsignedBigInteger('entity_id')->nullable();
            $t->string('path');
            $t->string('mime_type');
            $t->unsignedBigInteger('size');
            $t->string('sha256', 64);
            $t->timestamp('expires_at');
            $t->timestamps();
            $t->unique(['created_by', 'client_operation_id']);
        });
        $this->createTable('mobile_sync_seen_events', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained();
            $t->unsignedBigInteger('event_id');
            $t->timestamps();
            $t->unique(['user_id', 'event_id']);
        });
        if (! Schema::hasIndex('mobile_return_declaration_items', ['declaration_id', 'dispatch_item_id'], 'unique')) {
            Schema::table('mobile_return_declaration_items', fn (Blueprint $t) => $t->unique(['declaration_id', 'dispatch_item_id'], 'mobile_declaration_item_unique'));
        }
    }

    private function createTable(string $name, Closure $definition): void
    {
        if (! Schema::hasTable($name)) {
            Schema::create($name, $definition);
        }
    }

    public function down(): void
    {
        foreach (['mobile_sync_seen_events', 'mobile_attachments', 'mobile_return_declaration_items', 'mobile_return_declarations', 'mobile_incidents', 'mobile_step_overrides', 'mobile_record_corrections', 'mobile_step_records', 'mobile_protocol_steps', 'mobile_event_protocols'] as $table) {
            Schema::dropIfExists($table);
        }
        Schema::table('event_dispatches', fn (Blueprint $t) => $t->dropColumn('mobile_return_revision'));
    }
};
