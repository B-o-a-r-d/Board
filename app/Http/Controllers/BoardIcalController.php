<?php

namespace App\Http\Controllers;

use App\Models\Board;
use App\Services\IcalFeedService;
use Symfony\Component\HttpFoundation\Response;

class BoardIcalController extends Controller
{
    /**
     * Serve a board's dated cards as an iCal feed (public, resolved by the
     * board's revocable ical_token).
     */
    public function __invoke(string $token, IcalFeedService $ical): Response
    {
        abort_unless((bool) config('board.ical_feeds'), 404);

        $board = Board::query()
            ->whereNull('archived_at')
            ->where('ical_token', $token)
            ->firstOrFail();

        $cards = $board->cards()
            ->whereNull('archived_at')
            ->where(fn ($q) => $q->whereNotNull('due_at')->orWhereNotNull('start_at'))
            ->with(['board', 'list'])
            ->get();

        return response($ical->build($board->name, $cards), 200, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => 'inline; filename="'.($board->slug ?: 'board').'.ics"',
            'Cache-Control' => 'no-cache, private',
        ]);
    }
}
