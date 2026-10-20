<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['users' => ['status'], 'employees' => ['status'], 'events' => ['status']] as $name => $columns) {
            foreach ($columns as $column) {
                if (! Schema::hasColumn($name, $column)) {
                    Schema::table($name, fn (Blueprint $t) => $t->string($column)->default($name === 'events' ? 'pending' : 'active')->index());
                }
            }
        }
        foreach (['employees', 'products', 'equipments', 'event_types'] as $name) {
            if (! Schema::hasColumn($name, 'image_url')) {
                Schema::table($name, fn (Blueprint $t) => $t->text('image_url')->nullable());
            }
        }
        foreach (['equipments', 'client_types', 'event_types', 'experience_levels'] as $name) {
            if (! Schema::hasColumn($name, 'active')) {
                Schema::table($name, fn (Blueprint $t) => $t->boolean('active')->default(true));
            }
        }
        Schema::table('employees', fn (Blueprint $t) => $t->unsignedBigInteger('experience_level_id')->nullable()->change());
        if (! Schema::hasColumn('equipments', 'category')) {
            Schema::table('equipments', fn (Blueprint $t) => $t->string('category')->nullable());
        }
        if (! Schema::hasColumn('client_types', 'discount')) {
            Schema::table('client_types', fn (Blueprint $t) => $t->decimal('discount', 5, 2)->default(0));
        }
        if (! Schema::hasColumn('equipment_package', 'required')) {
            Schema::table('equipment_package', fn (Blueprint $t) => $t->boolean('required')->default(true));
        }
        if (! Schema::hasColumn('package_requirements', 'active')) {
            Schema::table('package_requirements', fn (Blueprint $t) => $t->boolean('active')->default(true));
        }
        if (! Schema::hasTable('admin_media')) {
            Schema::create('admin_media', function (Blueprint $t) {
                $t->uuid('id')->primary();
                $t->foreignId('created_by')->constrained('users');
                $t->string('kind');
                $t->string('mime_type');
                $t->unsignedBigInteger('size');
                $t->string('path');
                $t->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_media');
        foreach (['users', 'employees', 'events'] as $name) {
            Schema::table($name, fn (Blueprint $t) => $t->dropColumn('status'));
        }
        foreach (['employees', 'products', 'equipments', 'event_types'] as $name) {
            Schema::table($name, fn (Blueprint $t) => $t->dropColumn('image_url'));
        }
        foreach (['equipments', 'client_types', 'event_types', 'experience_levels'] as $name) {
            Schema::table($name, fn (Blueprint $t) => $t->dropColumn('active'));
        }
        Schema::table('equipments', fn (Blueprint $t) => $t->dropColumn('category'));
        Schema::table('client_types', fn (Blueprint $t) => $t->dropColumn('discount'));
        Schema::table('equipment_package', fn (Blueprint $t) => $t->dropColumn('required'));
        Schema::table('package_requirements', fn (Blueprint $t) => $t->dropColumn('active'));
    }
};
