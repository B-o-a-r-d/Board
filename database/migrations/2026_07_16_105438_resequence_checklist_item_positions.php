<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Renumber checklist items per checklist ordered by (position, id): older
     * writes left duplicate positions, which made the display order unstable
     * (checking an item could move it). Done in PHP (no window functions) to
     * stay portable — volumes are small (items per checklist).
     */
    public function up(): void
    {
        DB::table('checklist_items')
            ->orderBy('checklist_id')
            ->orderBy('position')
            ->orderBy('id')
            ->get(['id', 'checklist_id', 'position'])
            ->groupBy('checklist_id')
            ->each(function ($items) {
                foreach ($items->values() as $index => $item) {
                    if ((int) $item->position !== $index) {
                        DB::table('checklist_items')->where('id', $item->id)->update(['position' => $index]);
                    }
                }
            });
    }

    public function down(): void
    {
        // Renumbering is not reversible (the original duplicates are gone) — no-op.
    }
};
