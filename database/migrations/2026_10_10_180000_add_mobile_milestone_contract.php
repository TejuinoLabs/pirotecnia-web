<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mobile_protocol_steps', function (Blueprint $table) {
            if (! Schema::hasColumn('mobile_protocol_steps', 'milestone_code')) {
                $table->string('milestone_code', 32)->nullable();
            }
            if (! Schema::hasColumn('mobile_protocol_steps', 'validation_rule')) {
                $table->string('validation_rule', 32)->nullable();
            }
            if (! Schema::hasColumn('mobile_protocol_steps', 'requires_notes')) {
                $table->boolean('requires_notes')->default(false);
            }
        });
        if (! Schema::hasIndex('mobile_protocol_steps', 'mobile_protocol_milestone_unique')) {
            Schema::table('mobile_protocol_steps', fn (Blueprint $table) => $table->unique(['protocol_id', 'milestone_code'], 'mobile_protocol_milestone_unique'));
        }
        Schema::table('events', function (Blueprint $table) {
            if (! Schema::hasColumn('events', 'operational_status')) {
                $table->string('operational_status', 32)->default('not_started');
            }
        });
        Schema::table('mobile_event_protocols', function (Blueprint $table) {
            if (! Schema::hasColumn('mobile_event_protocols', 'closing_notes')) {
                $table->text('closing_notes')->nullable();
            }
            foreach (['closure_occurred_at', 'closure_recorded_at'] as $column) {
                if (! Schema::hasColumn('mobile_event_protocols', $column)) {
                    $table->timestamp($column)->nullable();
                }
            }
        });
        // Existing snapshots, IDs, versions and records are deliberately unchanged.
    }

    public function down(): void
    {
        Schema::table('mobile_protocol_steps', function (Blueprint $table) {
            $table->dropUnique('mobile_protocol_milestone_unique');
            $table->dropColumn(['milestone_code', 'validation_rule', 'requires_notes']);
        });
        Schema::table('events', fn (Blueprint $table) => $table->dropColumn('operational_status'));
        Schema::table('mobile_event_protocols', fn (Blueprint $table) => $table->dropColumn(['closing_notes', 'closure_occurred_at', 'closure_recorded_at']));
    }
};
