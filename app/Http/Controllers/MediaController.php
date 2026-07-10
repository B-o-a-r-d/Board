<?php

namespace App\Http\Controllers;

use App\Models\Attachment;
use App\Models\Board;
use App\Models\BoardList;
use App\Models\Card;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

/**
 * Streams user-uploaded media (attachments, covers, backgrounds, avatars) from
 * a PRIVATE disk through an access-controlled endpoint. This replaces direct
 * public-disk URLs, which (a) leaked private-board files to anyone with the URL
 * and (b) let a stored SVG execute JavaScript same-origin. Every response
 * carries anti-XSS headers so an SVG opened directly cannot run scripts.
 */
class MediaController extends Controller
{
    /**
     * Applied to every media response: block MIME sniffing and, via an empty
     * `sandbox`, neutralise scripts if an SVG is opened as a top-level document.
     *
     * @var array<string, string>
     */
    private const SECURITY_HEADERS = [
        'X-Content-Type-Options' => 'nosniff',
        'Content-Security-Policy' => "default-src 'none'; style-src 'unsafe-inline'; sandbox",
    ];

    /**
     * Card attachment — members only (never shown on public boards).
     */
    public function attachment(Attachment $attachment): Response
    {
        $this->authorize('view', $attachment->card->board);

        return $this->serve($attachment->disk, $attachment->path, $attachment->name, $attachment->mime_type);
    }

    /**
     * User avatar — any authenticated user (avatars are shown across shared boards).
     */
    public function avatar(User $user): Response
    {
        abort_if($user->avatar_path === null, 404);

        return $this->serve('local', $user->avatar_path, 'avatar');
    }

    public function cardCover(Request $request, Card $card): Response
    {
        $this->authorizeBoardMedia($request, $card->board);
        abort_if($card->cover_path === null, 404);

        return $this->serve('local', $card->cover_path, 'cover');
    }

    public function listCover(Request $request, BoardList $list): Response
    {
        $this->authorizeBoardMedia($request, $list->board);
        abort_if($list->cover_path === null, 404);

        return $this->serve('local', $list->cover_path, 'cover');
    }

    public function boardBackground(Request $request, Board $board): Response
    {
        $this->authorizeBoardMedia($request, $board);
        abort_if($board->background_image === null, 404);

        return $this->serve('local', $board->background_image, 'background');
    }

    /**
     * Board-scoped media is reachable either by a member (policy `view`) or by a
     * guest presenting the board's current share token while public sharing is
     * enabled — mirroring the read-only public board page itself.
     */
    private function authorizeBoardMedia(Request $request, Board $board): void
    {
        $user = $request->user();

        if ($user !== null && Gate::forUser($user)->allows('view', $board)) {
            return;
        }

        $token = (string) $request->query('t', '');

        if (config('board.public_sharing') && $board->share_token !== null && hash_equals($board->share_token, $token)) {
            return;
        }

        abort(403);
    }

    /**
     * Stream a file from the given disk with security headers. Local files use a
     * BinaryFileResponse so HTTP range requests (video seeking) keep working.
     */
    private function serve(string $disk, string $path, string $downloadName, ?string $mime = null): Response
    {
        $storage = Storage::disk($disk);

        abort_unless($storage->exists($path), 404);

        $mime ??= ($storage->mimeType($path) ?: 'application/octet-stream');

        $headers = self::SECURITY_HEADERS + [
            'Content-Type' => $mime,
            'Content-Disposition' => 'inline; filename="'.str_replace(['"', "\r", "\n"], '', $downloadName).'"',
        ];

        if (config("filesystems.disks.{$disk}.driver") === 'local') {
            return response()->file($storage->path($path), $headers);
        }

        return $storage->response($path, $downloadName, $headers);
    }
}
