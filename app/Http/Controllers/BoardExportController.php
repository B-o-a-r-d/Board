<?php

namespace App\Http\Controllers;

use App\Models\Board;
use App\Services\BoardExportService;
use Illuminate\Support\Str;
use Rap2hpoutre\FastExcel\FastExcel;
use Symfony\Component\HttpFoundation\Response;

class BoardExportController extends Controller
{
    public function __invoke(Board $board, string $format, BoardExportService $service): Response
    {
        $this->authorize('view', $board);

        abort_unless(in_array($format, ['csv', 'xlsx', 'json'], true), 404);

        $filename = 'board-'.(Str::slug($board->name) ?: 'export').'.'.$format;

        if ($format === 'json') {
            return response()
                ->json($service->structured($board), 200, [], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
                ->header('Content-Disposition', 'attachment; filename="'.$filename.'"');
        }

        return (new FastExcel($service->rows($board)))->download($filename);
    }
}
