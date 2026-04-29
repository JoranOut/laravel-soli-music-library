<?php

namespace App\Http\Controllers\Muziekstukken;

use App\Http\Controllers\Controller;
use App\Models\DownloadLog;
use App\Models\Part;
use App\Models\Piece;
use App\Services\MusicAccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PartController extends Controller
{
    public function store(Request $request, Piece $piece): RedirectResponse
    {
        $validated = $request->validate([
            'parts' => ['required', 'array', 'min:1'],
            'parts.*.file' => ['required', 'file', 'mimes:pdf', 'max:10240'],
            'parts.*.instrument_type_id' => ['required', 'integer', 'exists:instrument_types,id'],
            'parts.*.is_conductor' => ['sometimes', 'boolean'],
            'parts.*.voice' => ['nullable', 'integer', 'min:1'],
            'parts.*.amount_bought' => ['nullable', 'integer', 'min:0'],
            'parts.*.note' => ['nullable', 'string', 'max:20'],
        ]);

        foreach ($validated['parts'] as $partData) {
            $file = $partData['file'];
            $path = $file->store("pieces/{$piece->id}", 'sheets');

            $piece->parts()->create([
                'instrument_type_id' => $partData['instrument_type_id'],
                'is_conductor' => $partData['is_conductor'] ?? false,
                'voice' => $partData['voice'] ?? null,
                'amount_bought' => $partData['amount_bought'] ?? null,
                'note' => $partData['note'] ?? null,
                'file_path' => $path,
                'original_filename' => $file->getClientOriginalName(),
            ]);
        }

        return back();
    }

    public function update(Request $request, Piece $piece, Part $part): RedirectResponse
    {
        if ($part->piece_id !== $piece->id) {
            abort(404);
        }

        $validated = $request->validate([
            'instrument_type_id' => ['required', 'integer', 'exists:instrument_types,id'],
            'is_conductor' => ['required', 'boolean'],
            'voice' => ['nullable', 'integer', 'min:1'],
            'amount_bought' => ['nullable', 'integer', 'min:0'],
            'note' => ['nullable', 'string', 'max:20'],
        ]);

        $part->update($validated);

        return back();
    }

    public function destroy(Piece $piece, Part $part): RedirectResponse
    {
        if ($part->piece_id !== $piece->id) {
            abort(404);
        }

        Storage::disk('sheets')->delete($part->file_path);
        $part->delete();

        return back();
    }

    public function downloadUrl(Part $part): JsonResponse
    {
        $access = app(MusicAccessService::class);
        $visibleIds = $access->visibleParts($part->piece)->pluck('id')->toArray();

        if (! in_array($part->id, $visibleIds)) {
            abort(403);
        }

        return response()->json([
            'url' => URL::temporarySignedRoute('parts.download', now()->addDay(), ['part' => $part->id]),
        ]);
    }

    public function download(Request $request, Part $part): StreamedResponse
    {
        $access = app(MusicAccessService::class);
        $piece = $part->piece;

        $visibleIds = $access->visibleParts($piece)->pluck('id')->toArray();

        if (! in_array($part->id, $visibleIds)) {
            abort(403);
        }

        DownloadLog::create([
            'user_id' => $request->user()->id,
            'part_id' => $part->id,
            'downloaded_at' => now(),
            'ip' => $request->ip(),
        ]);

        $part->loadMissing('instrumentType');
        $downloadName = str($piece->title)->slug().'-'.str($part->instrumentType->name)->slug();
        if ($part->voice !== null) {
            $downloadName .= '-'.$part->voice;
        }
        if ($part->note !== null && $part->note !== '') {
            $downloadName .= '-'.str($part->note)->slug();
        }
        $downloadName .= '.pdf';

        return Storage::disk('sheets')->download($part->file_path, $downloadName);
    }
}
