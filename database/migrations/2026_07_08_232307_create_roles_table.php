<?php

use App\Models\Workspace;
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
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->string('key'); // slug: system keys (owner/admin/member/observer) or a custom slug
            $table->string('name');
            $table->json('permissions'); // array of Permission values
            $table->boolean('is_system')->default(false);
            $table->string('color', 7)->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();

            $table->unique(['workspace_id', 'key']);
        });

        // Backfill the system roles for workspaces that already exist (new ones
        // seed themselves via the Workspace "created" model hook).
        Workspace::query()->each(fn (Workspace $workspace) => $workspace->seedDefaultRoles());
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('roles');
    }
};
