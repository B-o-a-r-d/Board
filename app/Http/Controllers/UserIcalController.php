<?php

namespace App\Http\Controllers;

use App\Models\Board;
use App\Models\Card;
use App\Models\User;
use App\Services\IcalFeedService;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class UserIcalController extends Controller
{
    /**
     * Serve, as one iCal feed, the dated cards across every board a user can view
     * (public, resolved by the user's revocable ical_token; RBAC still applies).
     */
    public function __invoke(string $token, IcalFeedService $ical): Response
    {
        abort_unless((bool) config('board.ical_feeds'), 404);

        $user = User::where('ical_token', $token)->firstOrFail();

        // Candidate boards: those in the user's workspaces or where they're a member,
        // then filtered by the view policy (private / observer / deactivation all apply).
        $boardIds = Board::query()
            ->whereNull('archived_at')
            ->where(function ($q) use ($user) {
                $q->whereIn('workspace_id', $user->workspaces()->pluck('workspaces.id'))
                    ->orWhereHas('members', fn ($m) => $m->whereKey($user->getKey()));
            })
            ->get()
            ->filter(fn (Board $board): bool => Gate::forUser($user)->allows('view', $board))
            ->pluck('id');

        $cards = Card::query()
            ->whereIn('board_id', $boardIds)
            ->whereNull('archived_at')
            ->where(fn ($q) => $q->whereNotNull('due_at')->orWhereNotNull('start_at'))
            ->with(['board', 'list'])
            ->get();

        return response($ical->build($user->name.' — '.config('app.name'), $cards), 200, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => 'inline; filename="board-calendar.ics"',
            'Cache-Control' => 'no-cache, private',
        ]);
    }
}
