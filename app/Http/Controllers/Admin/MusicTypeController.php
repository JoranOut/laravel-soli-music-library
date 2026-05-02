<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MusicType;
use App\Models\Piece;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class MusicTypeController extends Controller
{
    public function index(): Response
    {
        $musicTypes = MusicType::orderBy('sort_order')->orderBy('name')->get();

        $musicTypes->each(function (MusicType $musicType) {
            $musicType->piece_count = Piece::where('music_type', $musicType->name)->count();
        });

        return Inertia::render('admin/music-types', [
            'musicTypes' => $musicTypes,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:music_types,name'],
            'sort_order' => ['nullable', 'integer'],
        ]);

        $validated['sort_order'] = $validated['sort_order'] ?? MusicType::max('sort_order') + 1;

        MusicType::create($validated);

        return back();
    }

    public function update(Request $request, MusicType $musicType): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('music_types', 'name')->ignore($musicType->id)],
            'sort_order' => ['nullable', 'integer'],
        ]);

        $oldName = $musicType->name;
        $musicType->update($validated);

        // If name changed, update all pieces that reference this music type
        if ($oldName !== $musicType->name) {
            Piece::where('music_type', $oldName)->update(['music_type' => $musicType->name]);
        }

        return back();
    }

    public function destroy(Request $request, MusicType $musicType): RedirectResponse
    {
        $request->validate([
            'replace_with_id' => ['nullable', 'integer', 'exists:music_types,id'],
        ]);

        $replacementId = $request->input('replace_with_id');

        if ($replacementId) {
            $replacement = MusicType::findOrFail($replacementId);
            Piece::where('music_type', $musicType->name)->update(['music_type' => $replacement->name]);
        } else {
            Piece::where('music_type', $musicType->name)->update(['music_type' => null]);
        }

        $musicType->delete();

        return back();
    }
}
