<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('card_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('card_id')->constrained()->cascadeOnDelete();
            $table->foreignId('related_card_id')->constrained('cards')->cascadeOnDelete();
            // Canonical types stored: 'blocks' (card_id blocks related_card_id) or
            // 'relates_to' (symmetric; stored with the smaller card id first).
            $table->string('type', 20);
            $table->timestamps();

            $table->unique(['card_id', 'related_card_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('card_links');
    }
};
