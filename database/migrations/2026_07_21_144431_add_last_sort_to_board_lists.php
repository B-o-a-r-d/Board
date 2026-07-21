<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Last applied sort of a list (criterion + direction): drives the arrow in
     * the list menu and lets a second click on the same criterion invert it.
     */
    public function up(): void
    {
        Schema::table('board_lists', function (Blueprint $table) {
            $table->string('last_sorted_by')->nullable()->after('wip_limit');
            $table->string('last_sorted_dir')->nullable()->after('last_sorted_by');
        });
    }

    public function down(): void
    {
        Schema::table('board_lists', function (Blueprint $table) {
            $table->dropColumn(['last_sorted_by', 'last_sorted_dir']);
        });
    }
};
