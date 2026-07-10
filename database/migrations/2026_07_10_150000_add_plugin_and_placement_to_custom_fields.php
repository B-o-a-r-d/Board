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
        Schema::table('custom_fields', function (Blueprint $table) {
            // Plugin-managed fields: which Power-Up injected the field and its
            // stable key inside that plugin (used to sync on install/update).
            $table->string('plugin_key', 60)->nullable()->after('board_id');
            $table->string('field_key', 60)->nullable()->after('plugin_key');
            // Where the field renders in the card modal: sidebar | content.
            $table->string('placement', 20)->default('sidebar')->after('options');

            $table->index(['board_id', 'plugin_key']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('custom_fields', function (Blueprint $table) {
            $table->dropIndex(['board_id', 'plugin_key']);
            $table->dropColumn(['plugin_key', 'field_key', 'placement']);
        });
    }
};
