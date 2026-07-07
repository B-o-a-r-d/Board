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
        Schema::create('board_plugins', function (Blueprint $table) {
            $table->id();
            $table->string('public_id', 26)->unique();
            $table->foreignId('board_id')->constrained()->cascadeOnDelete();
            $table->string('plugin_key');
            $table->string('name');
            $table->text('config')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['board_id', 'plugin_key']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('board_plugins');
    }
};
