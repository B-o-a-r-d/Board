<?php

namespace App\Console\Commands;

use App\Models\Card;
use App\Notifications\CardNotification;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('cards:notify-due')]
#[Description('Notify assigned members of cards due within the next 24 hours.')]
class NotifyDueCards extends Command
{
    public function handle(): int
    {
        $cards = Card::query()
            ->whereNull('completed_at')
            ->whereNull('archived_at')
            ->whereNotNull('due_at')
            ->whereBetween('due_at', [now(), now()->addDay()])
            ->with(['members', 'creator'])
            ->get();

        $sent = 0;

        foreach ($cards as $card) {
            foreach ($card->members as $member) {
                $alreadyNotified = $member->notifications()
                    ->where('type', CardNotification::class)
                    ->where('data->type', 'due_soon')
                    ->where('data->card_id', $card->id)
                    ->whereNull('read_at')
                    ->exists();

                if ($alreadyNotified) {
                    continue;
                }

                $member->notify(new CardNotification($card, 'due_soon', $card->creator ?? $member));
                $sent++;
            }
        }

        $this->info("Sent {$sent} due-soon notification(s).");

        return self::SUCCESS;
    }
}
