<?php

namespace App\Console\Commands;

use App\Models\Attachment;
use App\Models\Board;
use App\Models\BoardList;
use App\Models\Card;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Moves user-uploaded media (attachments, card/list covers, board backgrounds,
 * avatars) from the world-readable `public` disk to the private `local` disk,
 * now that they are served through the access-controlled MediaController.
 * Idempotent: a file already on `local` (or missing from `public`) is skipped.
 */
class MigrateMediaToPrivateDisk extends Command
{
    protected $signature = 'media:migrate-to-private {--dry-run : List what would move without touching files}';

    protected $description = 'Move uploaded media from the public disk to the private local disk';

    public function handle(): int
    {
        $public = Storage::disk('public');
        $local = Storage::disk('local');
        $dryRun = (bool) $this->option('dry-run');
        $moved = 0;
        $skipped = 0;

        $move = function (?string $path) use ($public, $local, $dryRun, &$moved, &$skipped): void {
            if ($path === null || $path === '') {
                return;
            }

            if ($local->exists($path) || ! $public->exists($path)) {
                $skipped++;

                return;
            }

            if ($dryRun) {
                $this->line("  would move: {$path}");
                $moved++;

                return;
            }

            $local->put($path, $public->get($path));
            $public->delete($path);
            $moved++;
        };

        $this->info('Attachments…');
        Attachment::query()->where('disk', 'public')->chunkById(200, function ($attachments) use ($move, $dryRun): void {
            foreach ($attachments as $attachment) {
                $move($attachment->path);

                if (! $dryRun) {
                    $attachment->update(['disk' => 'local']);
                }
            }
        });

        $this->info('Card covers…');
        Card::query()->whereNotNull('cover_path')->chunkById(200, function ($cards) use ($move): void {
            foreach ($cards as $card) {
                $move($card->cover_path);
            }
        });

        $this->info('List covers…');
        BoardList::query()->whereNotNull('cover_path')->chunkById(200, function ($lists) use ($move): void {
            foreach ($lists as $list) {
                $move($list->cover_path);
            }
        });

        $this->info('Board backgrounds…');
        Board::query()->whereNotNull('background_image')->chunkById(200, function ($boards) use ($move): void {
            foreach ($boards as $board) {
                $move($board->background_image);
            }
        });

        $this->info('Avatars…');
        User::query()->whereNotNull('avatar_path')->chunkById(200, function ($users) use ($move): void {
            foreach ($users as $user) {
                $move($user->avatar_path);
            }
        });

        $this->newLine();
        $this->info(($dryRun ? '[dry-run] ' : '')."Done. Moved: {$moved}, skipped: {$skipped}.");

        return self::SUCCESS;
    }
}
