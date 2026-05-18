<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DownloadLog;
use Inertia\Inertia;
use Inertia\Response;

class DownloadLogController extends Controller
{
    public function index(): Response
    {
        $logs = DownloadLog::with(['user', 'part.piece', 'part.instrumentType'])
            ->orderByDesc('downloaded_at')
            ->paginate(20)
            ->through(fn (DownloadLog $log) => [
                'id' => $log->id,
                'user_name' => $log->user?->name,
                'piece_title' => $log->part?->piece?->title,
                'instrument' => $log->part?->instrumentType?->name,
                'voice' => $log->part?->voice,
                'filename' => $log->part?->original_filename,
                'downloaded_at' => $log->downloaded_at->toIso8601String(),
                'country' => $log->country,
            ]);

        return Inertia::render('admin/download-logs', [
            'logs' => $logs,
        ]);
    }
}
