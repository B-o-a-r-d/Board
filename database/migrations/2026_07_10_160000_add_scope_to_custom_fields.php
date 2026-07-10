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
            // Field scope: both null = board-wide (default), a list id = every
            // card of that list inherits it, a card id = that single card.
            $table->foreignId('board_list_id')->nullable()->after('board_id')->constrained()->cascadeOnDelete();
            $table->foreignId('card_id')->nullable()->after('board_list_id')->constrained()->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('custom_fields', function (Blueprint $table) {
            $table->dropConstrainedForeignId('board_list_id');
            $table->dropConstrainedForeignId('card_id');
        });
    }
};
