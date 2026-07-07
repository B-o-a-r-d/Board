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
        Schema::table('board_lists', function (Blueprint $table) {
            $table->foreignId('source_plugin_id')->nullable()->after('wip_limit')
                ->constrained('board_plugins')->nullOnDelete();
            $table->string('source_mode')->nullable()->after('source_plugin_id');
            $table->json('source_config')->nullable()->after('source_mode');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('board_lists', function (Blueprint $table) {
            $table->dropConstrainedForeignId('source_plugin_id');
            $table->dropColumn(['source_mode', 'source_config']);
        });
    }
};
