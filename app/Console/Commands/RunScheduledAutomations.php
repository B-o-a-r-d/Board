<?php

namespace App\Console\Commands;

use App\Automations\AutomationEngine;
use App\Models\Automation;
use App\Models\Card;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('automations:run-scheduled')]
#[Description('Fire time-based automation triggers (e.g. card due soon) for boards that use them.')]
class RunScheduledAutomations extends Command
{
    public function handle(AutomationEngine $engine): int
    {
        $boardIds = Automation::query()
            ->where('is_active', true)
            ->where('trigger_type', 'card.due_soon')
            ->distinct()
            ->pluck('board_id');

        if ($boardIds->isEmpty()) {
            $this->info('No boards use scheduled automations.');

            return self::SUCCESS;
        }

        $cards = Card::query()
            ->whereIn('board_id', $boardIds)
            ->whereNull('completed_at')
            ->whereNull('archived_at')
            ->whereNotNull('due_at')
            ->whereBetween('due_at', [now()->subDay(), now()->addDay()])
            ->get();

        $ran = 0;

        foreach ($cards as $card) {
            $ran += $engine->fire('card.due_soon', $card);
        }

        $this->info("Ran {$ran} scheduled automation action(s) across {$cards->count()} card(s).");

        return self::SUCCESS;
    }
}
