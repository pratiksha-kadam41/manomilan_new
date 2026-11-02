<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('module_permissions', function (Blueprint $table): void {
                    $table->id();
                    $table->foreignId('role_id')->constrained('roles')->cascadeOnDelete();
                    $table->foreignId('module_id')->constrained('modules')->cascadeOnDelete();
                    foreach (['view', 'create', 'edit', 'delete', 'import', 'export', 'approve', 'manage'] as $action) {
                        $table->boolean('can_'.$action)->default(false);
                    }
                    $table->timestamps();
                    $table->unique(['role_id', 'module_id']);
                });
    }

    public function down(): void
    {
        Schema::dropIfExists('module_permissions');
    }
};
