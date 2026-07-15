<?php

namespace App\Livewire\Boards;

use App\Models\Board;
use Board\PluginSdk\Contracts\DefinesActivities;
use Board\PluginSdk\PluginRegistry;
use Illuminate\Contracts\View\View;
use Livewire\Component;

/**
 * The board's activity slide-over, extracted from the monolithic {@see Show}.
 *
 * The panel shell opens instantly client-side (Alpine `open`, entangled), and the
 * log itself is only queried when `open` is true — so opening it re-renders just
 * this small component instead of the whole board, and closed boards never pay the
 * activity query. Rows link back to their card via {@see focusActivity}.
 */
class ActivityLog extends Component
{
    public Board $board;

    /** Entangled with Alpine: the panel opens instantly, then this loads the log. */
    public bool $open = false;

    /** Viewing the log requires board-view access (parity with the old toggle). */
    public function updatedOpen(bool $value): void
    {
        if ($value) {
            $this->authorize('view', $this->board);
        }
    }

    /**
     * Jump from an activity row to its subject: close the slide-over and open the
     * target card, optionally focusing a comment or a named section.
     */
    public function focusActivity(int $cardId, ?string $section = null, ?int $comment = null): void
    {
        $this->authorize('view', $this->board);

        $this->open = false;
        $this->dispatch('open-card', cardId: $cardId, section: $section, comment: $comment);
    }

    /** Board admins set how long the activity log is kept (pruned daily). */
    public function saveActivityRetention(?string $days): void
    {
        $this->authorize('update', $this->board);

        $value = ($days === null || $days === '') ? null : max(0, (int) $days);
        $this->board->update(['activity_retention_days' => $value ?: null]);

        $this->dispatch('toast', message: __('Rétention du journal mise à jour'), type: 'success');
    }

    public function render(): View
    {
        // Only pay for the log (and the plugin-tab probes) while the panel is open.
        $activities = $this->open
            ? $this->board->activities()->with(['user', 'card'])->latest()->limit(60)->get()
            : collect();

        $activityTabs = [];

        if ($this->open) {
            foreach (app(PluginRegistry::class)->all() as $key => $plugin) {
                if ($plugin instanceof DefinesActivities
                    && $this->board->activities()->whereIn('type', $plugin->activityTypes())->exists()) {
                    $activityTabs[] = ['plugin_key' => $key, 'label' => $plugin->activityTab()['label']];
                }
            }
        }

        return view('livewire.boards.activity-log', [
            'activities' => $activities,
            'activityTabs' => $activityTabs,
        ]);
    }
}
