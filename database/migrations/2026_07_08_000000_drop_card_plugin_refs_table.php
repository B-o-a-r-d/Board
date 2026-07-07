<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * Removes the card-enrichment ("Liens externes") feature: plugins no longer
 * attach external references (commits/PRs/issues) to cards.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('card_plugin_refs');
    }

    public function down(): void
    {
        // The card-enrichment feature was removed; nothing to restore.
    }
};
