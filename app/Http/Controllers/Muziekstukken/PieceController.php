<?php

namespace App\Http\Controllers\Muziekstukken;

use App\Http\Controllers\Controller;
use App\Models\InstrumentType;
use App\Models\Orchestra;
use App\Models\Piece;
use App\Services\MusicAccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PieceController extends Controller
{
    public function index(Request $request): Response
    {
        $access = app(MusicAccessService::class);
        $canEdit = $access->isEditor();

        $query = Piece::with('orchestras')->withCount('parts');

        // Members only see pieces from their orchestras
        if (! $canEdit && ! $access->isDirigent()) {
            $orchestraIds = $access->getOrchestraIds();
            $query->whereHas('orchestras', fn ($q) => $q->whereIn('orchestras.id', $orchestraIds));
        }

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('composer', 'like', "%{$search}%");
            });
        }

        if ($orchestraId = $request->input('orchestra')) {
            $query->whereHas('orchestras', fn ($q) => $q->where('orchestras.id', $orchestraId));
        }

        if ($instruments = $request->input('instruments')) {
            $ids = explode(',', $instruments);
            $query->whereHas('parts', fn ($q) => $q->whereIn('instrument_type_id', $ids));
        }

        if ($composer = $request->input('composer')) {
            $query->where('composer', $composer);
        }

        if ($arranger = $request->input('arranger')) {
            $query->where('arranger', $arranger);
        }

        if ($publisher = $request->input('publisher')) {
            $query->where('publisher', $publisher);
        }

        if ($musicType = $request->input('music_type')) {
            $query->where('music_type', $musicType);
        }

        if ($genre = $request->input('genre')) {
            $query->whereJsonContains('genre', $genre);
        }

        if ($difficulty = $request->input('difficulty')) {
            $query->where('difficulty', $difficulty);
        }

        if ($buyDateFrom = $request->input('buy_date_from')) {
            $query->where('buy_date', '>=', $buyDateFrom);
        }

        if ($buyDateTo = $request->input('buy_date_to')) {
            $query->where('buy_date', '<=', $buyDateTo);
        }

        $pieces = $query->orderBy('title')->paginate(20)->withQueryString()->through(fn ($piece) => array_merge(
            $piece->toArray(),
            [
                'audio_url' => $piece->audio_file_path
                    ? URL::temporarySignedRoute('muziekstukken.audio.stream', now()->addDay(), ['piece' => $piece->id])
                    : null,
            ],
        ));

        return Inertia::render('muziekstukken/index', [
            'pieces' => $pieces,
            'orchestras' => Orchestra::where('is_active', true)->orderBy('sort_order')->get(),
            'instrumentTypes' => InstrumentType::with('instrumentFamily')->orderBy('sort_order')->get(),
            'filterOptions' => [
                'composers' => Piece::whereNotNull('composer')->where('composer', '!=', '')->distinct()->pluck('composer')->sort()->values(),
                'arrangers' => Piece::whereNotNull('arranger')->where('arranger', '!=', '')->distinct()->pluck('arranger')->sort()->values(),
                'publishers' => Piece::whereNotNull('publisher')->where('publisher', '!=', '')->distinct()->pluck('publisher')->sort()->values(),
                'musicTypes' => Piece::whereNotNull('music_type')->where('music_type', '!=', '')->distinct()->pluck('music_type')->sort()->values(),
                'genres' => Piece::whereNotNull('genre')->pluck('genre')->flatten()->unique()->sort()->values(),
                'difficulties' => Piece::whereNotNull('difficulty')->where('difficulty', '!=', '')->distinct()->pluck('difficulty')->sort()->values(),
            ],
            'filters' => $request->only(['search', 'orchestra', 'instruments', 'composer', 'arranger', 'publisher', 'music_type', 'genre', 'difficulty', 'buy_date_from', 'buy_date_to']),
            'canEdit' => $canEdit,
            'canEditUsages' => $canEdit || $access->isDirigent(),
        ]);
    }

    public function show(Piece $piece): Response
    {
        $access = app(MusicAccessService::class);
        $piece->load(['orchestras', 'orchestraUsages.orchestra']);

        $parts = $access->visibleParts($piece)->map(fn ($part) => [
            ...$part->toArray(),
            'download_url' => URL::temporarySignedRoute('parts.download', now()->addDay(), ['part' => $part->id]),
        ]);

        $audioUrl = $piece->audio_file_path
            ? URL::temporarySignedRoute('muziekstukken.audio.stream', now()->addDay(), ['piece' => $piece->id])
            : null;

        return Inertia::render('muziekstukken/show', [
            'piece' => $piece,
            'parts' => $parts,
            'audioUrl' => $audioUrl,
            'instrumentTypes' => InstrumentType::with('instrumentFamily')->orderBy('sort_order')->get(),
            'canEdit' => $access->isEditor() || $access->isDirigent(),
        ]);
    }

    public function suggestions(): JsonResponse
    {
        $genres = Piece::whereNotNull('genre')
            ->pluck('genre')
            ->flatten()
            ->unique()
            ->sort()
            ->values();

        $musicTypes = Piece::whereNotNull('music_type')
            ->distinct()
            ->pluck('music_type')
            ->sort()
            ->values();

        return response()->json([
            'genres' => $genres,
            'musicTypes' => $musicTypes,
        ]);
    }

    public function create(): Response
    {
        $suggestions = $this->getSuggestions();

        return Inertia::render('muziekstukken/create', [
            'orchestras' => Orchestra::where('is_active', true)->orderBy('sort_order')->get(),
            'genreSuggestions' => $suggestions['genres'],
            'musicTypeSuggestions' => $suggestions['musicTypes'],
            'composerSuggestions' => $suggestions['composers'],
            'arrangerSuggestions' => $suggestions['arrangers'],
            'publisherSuggestions' => $suggestions['publishers'],
            'difficultySuggestions' => $suggestions['difficulties'],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'composer' => ['nullable', 'string', 'max:255'],
            'arranger' => ['nullable', 'string', 'max:255'],
            'publisher' => ['nullable', 'string', 'max:255'],
            'difficulty' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
            'bought_for' => ['nullable', 'string', 'max:255'],
            'buy_date' => ['nullable', 'date'],
            'genre' => ['nullable', 'array'],
            'genre.*' => ['string', 'max:255'],
            'music_type' => ['nullable', 'string', 'max:255'],
            'archive_number' => ['nullable', 'string', 'max:255'],
            'audio_youtube_url' => ['nullable', 'url', 'max:500'],
            'orchestras' => ['nullable', 'array'],
            'orchestras.*' => ['integer', 'exists:orchestras,id'],
        ]);

        $piece = Piece::create(collect($validated)->except('orchestras')->toArray());

        foreach ($validated['orchestras'] ?? [] as $orchestraId) {
            $piece->orchestraUsages()->create(['orchestra_id' => $orchestraId]);
        }

        return redirect("/muziekstukken/{$piece->id}/edit");
    }

    public function edit(Piece $piece): Response
    {
        $access = app(MusicAccessService::class);
        $canEditAllFields = $access->isEditor();

        $piece->load(['orchestras', 'orchestraUsages.orchestra', 'parts.instrumentType.instrumentFamily']);

        $suggestions = $this->getSuggestions();

        $audioUrl = $piece->audio_file_path
            ? URL::temporarySignedRoute('muziekstukken.audio.stream', now()->addDay(), ['piece' => $piece->id])
            : null;

        $props = [
            'piece' => $piece,
            'audioUrl' => $audioUrl,
            'orchestras' => Orchestra::where('is_active', true)->orderBy('sort_order')->get(),
            'canEditAllFields' => $canEditAllFields,
            'canArchive' => $canEditAllFields,
            'genreSuggestions' => $suggestions['genres'],
            'musicTypeSuggestions' => $suggestions['musicTypes'],
            'composerSuggestions' => $suggestions['composers'],
            'arrangerSuggestions' => $suggestions['arrangers'],
            'publisherSuggestions' => $suggestions['publishers'],
            'difficultySuggestions' => $suggestions['difficulties'],
        ];

        if ($canEditAllFields) {
            $props['instrumentTypes'] = InstrumentType::with('instrumentFamily')
                ->orderBy('sort_order')
                ->get();
        }

        return Inertia::render('muziekstukken/edit', $props);
    }

    public function update(Request $request, Piece $piece): RedirectResponse
    {
        $access = app(MusicAccessService::class);

        if ($access->isEditor()) {
            $validated = $request->validate([
                'title' => ['required', 'string', 'max:255'],
                'composer' => ['nullable', 'string', 'max:255'],
                'arranger' => ['nullable', 'string', 'max:255'],
                'publisher' => ['nullable', 'string', 'max:255'],
                'difficulty' => ['nullable', 'string', 'max:255'],
                'notes' => ['nullable', 'string'],
                'bought_for' => ['nullable', 'string', 'max:255'],
                'buy_date' => ['nullable', 'date'],
                'genre' => ['nullable', 'array'],
                'genre.*' => ['string', 'max:255'],
                'music_type' => ['nullable', 'string', 'max:255'],
                'archive_number' => ['nullable', 'string', 'max:255'],
                'audio_youtube_url' => ['nullable', 'url', 'max:500'],
                'usages' => ['nullable', 'array'],
                'usages.*.id' => ['nullable', 'integer'],
                'usages.*.orchestra_id' => ['required', 'integer', 'exists:orchestras,id'],
                'usages.*.van' => ['nullable', 'date'],
                'usages.*.tot' => ['nullable', 'date'],
                'usages.*.details' => ['nullable', 'string'],
                'parts' => ['nullable', 'array'],
                'parts.*.id' => ['required', 'integer'],
                'parts.*.instrument_type_id' => ['required', 'integer', 'exists:instrument_types,id'],
                'parts.*.is_conductor' => ['required', 'boolean'],
                'parts.*.voice' => ['nullable', 'integer', 'min:1'],
                'parts.*.amount_bought' => ['nullable', 'integer', 'min:0'],
                'parts.*.note' => ['nullable', 'string', 'max:20'],
            ]);

            // When setting a YouTube URL, clear any existing MP3 file
            if (! empty($validated['audio_youtube_url']) && $piece->audio_file_path) {
                Storage::disk('sheets')->delete($piece->audio_file_path);
                $validated['audio_file_path'] = null;
            }

            $piece->update(collect($validated)->except('usages', 'parts')->toArray());
            $this->syncUsages($piece, $validated['usages'] ?? []);

            foreach ($validated['parts'] ?? [] as $partData) {
                $piece->parts()->where('id', $partData['id'])->firstOrFail()->update([
                    'instrument_type_id' => $partData['instrument_type_id'],
                    'is_conductor' => $partData['is_conductor'],
                    'voice' => $partData['voice'] ?? null,
                    'amount_bought' => $partData['amount_bought'] ?? null,
                    'note' => $partData['note'] ?? null,
                ]);
            }
        } else {
            // Dirigent: can only update usages
            $validated = $request->validate([
                'usages' => ['nullable', 'array'],
                'usages.*.id' => ['nullable', 'integer'],
                'usages.*.orchestra_id' => ['required', 'integer', 'exists:orchestras,id'],
                'usages.*.van' => ['nullable', 'date'],
                'usages.*.tot' => ['nullable', 'date'],
                'usages.*.details' => ['nullable', 'string'],
            ]);

            $this->syncUsages($piece, $validated['usages'] ?? []);
        }

        return redirect()->route('muziekstukken.show', $piece);
    }

    public function storeUsage(Request $request, Piece $piece): RedirectResponse
    {
        $validated = $request->validate([
            'orchestra_id' => ['required', 'integer', 'exists:orchestras,id'],
            'van' => ['nullable', 'date'],
            'tot' => ['nullable', 'date'],
            'details' => ['nullable', 'string'],
        ]);

        $piece->orchestraUsages()->create($validated);

        return back();
    }

    public function archive(Piece $piece): RedirectResponse
    {
        $piece->delete();

        return redirect('/muziekstukken');
    }

    public function restore(int $piece): RedirectResponse
    {
        $piece = Piece::withTrashed()->findOrFail($piece);
        $piece->restore();

        return redirect("/muziekstukken/{$piece->id}/edit");
    }

    public function destroy(Piece $piece): RedirectResponse
    {
        if ($piece->audio_file_path) {
            Storage::disk('sheets')->delete($piece->audio_file_path);
        }

        foreach ($piece->parts as $part) {
            Storage::disk('sheets')->delete($part->file_path);
        }

        $piece->forceDelete();

        return redirect('/muziekstukken');
    }

    public function updateAudio(Request $request, Piece $piece): RedirectResponse
    {
        $request->validate([
            'audio' => ['required', 'file', 'mimes:mp3', 'max:51200'],
        ]);

        $path = "pieces/{$piece->id}/audio.mp3";

        Storage::disk('sheets')->put($path, file_get_contents($request->file('audio')->getRealPath()));

        $piece->update([
            'audio_file_path' => $path,
            'audio_youtube_url' => null,
        ]);

        return back();
    }

    public function deleteAudio(Piece $piece): RedirectResponse
    {
        if ($piece->audio_file_path) {
            Storage::disk('sheets')->delete($piece->audio_file_path);
        }

        $piece->update([
            'audio_file_path' => null,
            'audio_youtube_url' => null,
        ]);

        return back();
    }

    public function streamAudio(Request $request, Piece $piece): StreamedResponse
    {
        abort_unless($piece->audio_file_path && Storage::disk('sheets')->exists($piece->audio_file_path), 404);

        $disk = Storage::disk('sheets');
        $size = $disk->size($piece->audio_file_path);
        $headers = [
            'Content-Type' => 'audio/mpeg',
            'Accept-Ranges' => 'bytes',
        ];

        $range = $request->header('Range');
        if ($range && preg_match('/bytes=(\d+)-(\d*)/', $range, $matches)) {
            $start = (int) $matches[1];
            $end = $matches[2] !== '' ? (int) $matches[2] : $size - 1;
            $end = min($end, $size - 1);
            $length = $end - $start + 1;

            $stream = $disk->readStream($piece->audio_file_path);
            fseek($stream, $start);

            return response()->stream(function () use ($stream, $length) {
                $remaining = $length;
                while ($remaining > 0 && ! feof($stream)) {
                    $chunk = fread($stream, min(8192, $remaining));
                    if ($chunk === false) {
                        break;
                    }
                    echo $chunk;
                    $remaining -= strlen($chunk);
                }
                fclose($stream);
            }, 206, array_merge($headers, [
                'Content-Range' => "bytes {$start}-{$end}/{$size}",
                'Content-Length' => $length,
            ]));
        }

        return $disk->response($piece->audio_file_path, 'audio.mp3', array_merge($headers, [
            'Content-Length' => $size,
        ]));
    }

    /** @param array<int, array{id?: int|null, orchestra_id: int, van?: string|null, tot?: string|null, details?: string|null}> $usages */
    private function syncUsages(Piece $piece, array $usages): void
    {
        $incomingIds = collect($usages)->pluck('id')->filter()->toArray();

        // Delete omitted records
        $piece->orchestraUsages()->whereNotIn('id', $incomingIds)->delete();

        foreach ($usages as $usageData) {
            if (! empty($usageData['id'])) {
                // Update existing
                $piece->orchestraUsages()->where('id', $usageData['id'])->update([
                    'orchestra_id' => $usageData['orchestra_id'],
                    'van' => $usageData['van'] ?? null,
                    'tot' => $usageData['tot'] ?? null,
                    'details' => $usageData['details'] ?? null,
                ]);
            } else {
                // Create new
                $piece->orchestraUsages()->create([
                    'orchestra_id' => $usageData['orchestra_id'],
                    'van' => $usageData['van'] ?? null,
                    'tot' => $usageData['tot'] ?? null,
                    'details' => $usageData['details'] ?? null,
                ]);
            }
        }
    }

    private function getSuggestions(): array
    {
        $genres = Piece::whereNotNull('genre')
            ->pluck('genre')
            ->flatten()
            ->unique()
            ->sort()
            ->values();

        $musicTypes = Piece::whereNotNull('music_type')
            ->distinct()
            ->pluck('music_type')
            ->sort()
            ->values();

        $composers = Piece::whereNotNull('composer')->where('composer', '!=', '')
            ->distinct()->pluck('composer')->sort()->values();

        $arrangers = Piece::whereNotNull('arranger')->where('arranger', '!=', '')
            ->distinct()->pluck('arranger')->sort()->values();

        $publishers = Piece::whereNotNull('publisher')->where('publisher', '!=', '')
            ->distinct()->pluck('publisher')->sort()->values();

        $difficulties = Piece::whereNotNull('difficulty')->where('difficulty', '!=', '')
            ->distinct()->pluck('difficulty')->sort()->values();

        return [
            'genres' => $genres,
            'musicTypes' => $musicTypes,
            'composers' => $composers,
            'arrangers' => $arrangers,
            'publishers' => $publishers,
            'difficulties' => $difficulties,
        ];
    }
}
