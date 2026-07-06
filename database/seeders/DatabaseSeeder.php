<?php

namespace Database\Seeders;

use App\Enums\BoardVisibility;
use App\Enums\Role;
use App\Models\Board;
use App\Models\BoardList;
use App\Models\Card;
use App\Models\Checklist;
use App\Models\ChecklistItem;
use App\Models\Comment;
use App\Models\Label;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $owner = User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        $teammates = User::factory(4)->create();

        $workspace = Workspace::factory()->create([
            'owner_id' => $owner->id,
            'name' => 'Acme',
        ]);

        $workspace->members()->attach($owner, ['role' => Role::Owner->value]);
        $workspace->members()->attach($teammates[0], ['role' => Role::Admin->value]);
        $workspace->members()->attach($teammates->skip(1), ['role' => Role::Member->value]);

        $members = collect([$owner])->merge($teammates);

        $this->buildBoard($workspace, 'Roadmap Produit', BoardVisibility::Workspace, $owner, $members);
        $this->buildBoard($workspace, 'Sprint Équipe', BoardVisibility::Private, $owner, $members);
    }

    /**
     * @param  Collection<int, User>  $members
     */
    private function buildBoard(Workspace $workspace, string $name, BoardVisibility $visibility, User $owner, $members): void
    {
        $board = Board::factory()->create([
            'workspace_id' => $workspace->id,
            'created_by' => $owner->id,
            'name' => $name,
            'slug' => Str::slug($name),
            'visibility' => $visibility,
        ]);

        $board->members()->attach($owner, ['role' => Role::Owner->value]);
        $board->members()->attach($members->skip(1), ['role' => Role::Member->value]);

        $labels = Label::factory()
            ->count(4)
            ->sequence(
                ['name' => 'Urgent', 'color' => '#ef4444'],
                ['name' => 'Feature', 'color' => '#3b82f6'],
                ['name' => 'Bug', 'color' => '#f59e0b'],
                ['name' => 'Design', 'color' => '#a855f7'],
            )
            ->create(['board_id' => $board->id]);

        $listNames = ['À faire', 'En cours', 'En revue', 'Terminé'];

        foreach ($listNames as $listIndex => $listName) {
            $list = BoardList::factory()->create([
                'board_id' => $board->id,
                'name' => $listName,
                'position' => $listIndex,
            ]);

            foreach (range(1, random_int(2, 4)) as $cardIndex) {
                $card = Card::factory()->create([
                    'board_list_id' => $list->id,
                    'board_id' => $board->id,
                    'created_by' => $owner->id,
                    'position' => $cardIndex,
                ]);

                $card->members()->attach($members->random(random_int(1, 2)));
                $card->labels()->attach($labels->random(random_int(1, 2)));

                $checklist = Checklist::factory()->create(['card_id' => $card->id]);
                ChecklistItem::factory()->count(3)->create(['checklist_id' => $checklist->id]);

                Comment::factory()->count(random_int(0, 2))->create([
                    'card_id' => $card->id,
                    'user_id' => $members->random()->id,
                ]);
            }
        }
    }
}
