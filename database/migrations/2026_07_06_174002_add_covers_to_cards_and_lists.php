<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cards', function (Blueprint $table) {
            $table->string('cover_color', 9)->nullable()->after('cover_path');
        });

        Schema::table('board_lists', function (Blueprint $table) {
            $table->string('cover_color', 9)->nullable()->after('name');
        });
    }

    public function down(): void
    {
        Schema::table('cards', function (Blueprint $table) {
            $table->dropColumn('cover_color');
        });

        Schema::table('board_lists', function (Blueprint $table) {
            $table->dropColumn('cover_color');
        });
    }
};
