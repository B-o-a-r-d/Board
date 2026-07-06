<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('comments', function (Blueprint $table) {
            $table->json('hidden_previews')->nullable()->after('body');
        });

        Schema::table('cards', function (Blueprint $table) {
            $table->json('hidden_previews')->nullable()->after('description');
        });
    }

    public function down(): void
    {
        Schema::table('comments', function (Blueprint $table) {
            $table->dropColumn('hidden_previews');
        });

        Schema::table('cards', function (Blueprint $table) {
            $table->dropColumn('hidden_previews');
        });
    }
};
