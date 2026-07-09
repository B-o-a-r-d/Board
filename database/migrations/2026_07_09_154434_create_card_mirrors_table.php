<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A mirror places one existing card (card_id) into another list/board without
     * copying it — the same underlying card, shown in several places at once.
     */
    public function up(): void
    {
        Schema::create('card_mirrors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('card_id')->constrained()->cascadeOnDelete();
            $table->foreignId('board_list_id')->constrained()->cascadeOnDelete();
            $table->foreignId('board_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();

            // A card can only be mirrored once into a given list.
            $table->unique(['card_id', 'board_list_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('card_mirrors');
    }
};
