<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('board_lists', function (Blueprint $table) {
            $table->string('cover_path')->nullable()->after('cover_color');
            $table->unsignedInteger('wip_limit')->nullable()->after('cover_path');
        });
    }

    public function down(): void
    {
        Schema::table('board_lists', function (Blueprint $table) {
            $table->dropColumn(['cover_path', 'wip_limit']);
        });
    }
};
