<?php

namespace App\Console\Commands;

use App\Models\AutomationRun;
use App\Models\Board;
use Illuminate\Console\Command;

/**
 * Deletes activity-log entries older than each board's configured retention
 * (`boards.activity_retention_days`, set by a board admin). A board with no
 * retention keeps its activity forever. Scheduled daily; can be run manually.
 */
class PruneActivities extends Command
{
    protected $signature = 'activities:prune';

    protected $description = "Prune activity-log entries older than each board's configured retention";

    public function handle(): int
    {
        $totalDeleted = 0;
        $boardCount = 0;

        Board::whereNotNull('activity_retention_days')
            ->where('activity_retention_days', '>', 0)
            ->chunkById(200, function ($boards) use (&$totalDeleted, &$boardCount): void {
                foreach ($boards as $board) {
                    $deleted = $board->activities()
                        ->where('created_at', '<', now()->subDays($board->activity_retention_days))
                        ->delete();

                    if ($deleted > 0) {
                        $boardCount++;
                        $totalDeleted += $deleted;
                    }
                }
            });

        // Automation runs are a bounded, high-churn journal — keep 30 days.
        $runsDeleted = AutomationRun::where('created_at', '<', now()->subDays(30))->delete();

        $this->info("Pruned {$totalDeleted} activity entries across {$boardCount} board(s), and {$runsDeleted} automation run(s).");

        return self::SUCCESS;
    }
}
