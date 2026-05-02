<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Genre;
use App\Models\Piece;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class GenreController extends Controller
{
    public function index(): Response
    {
        $genres = Genre::orderBy('sort_order')->orderBy('name')->get();

        // Count pieces per genre using whereJsonContains
        $genres->each(function (Genre $genre) {
            $genre->piece_count = Piece::whereJsonContains('genre', $genre->name)->count();
        });

        return Inertia::render('admin/genres', [
            'genres' => $genres,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:genres,name'],
            'sort_order' => ['nullable', 'integer'],
        ]);

        $validated['sort_order'] = $validated['sort_order'] ?? Genre::max('sort_order') + 1;

        Genre::create($validated);

        return back();
    }

    public function update(Request $request, Genre $genre): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('genres', 'name')->ignore($genre->id)],
            'sort_order' => ['nullable', 'integer'],
        ]);

        $oldName = $genre->name;
        $genre->update($validated);

        // If name changed, update all pieces that reference this genre
        if ($oldName !== $genre->name) {
            $this->replaceGenreInPieces($oldName, $genre->name);
        }

        return back();
    }

    public function destroy(Request $request, Genre $genre): RedirectResponse
    {
        $request->validate([
            'replace_with_id' => ['nullable', 'integer', 'exists:genres,id'],
        ]);

        $replacementId = $request->input('replace_with_id');

        if ($replacementId) {
            $replacement = Genre::findOrFail($replacementId);
            $this->replaceGenreInPieces($genre->name, $replacement->name);
        } else {
            $this->removeGenreFromPieces($genre->name);
        }

        $genre->delete();

        return back();
    }

    private function replaceGenreInPieces(string $oldName, string $newName): void
    {
        Piece::whereJsonContains('genre', $oldName)->each(function (Piece $piece) use ($oldName, $newName) {
            $genres = $piece->genre ?? [];
            $genres = array_map(fn (string $g) => $g === $oldName ? $newName : $g, $genres);
            $genres = array_values(array_unique($genres));
            $piece->update(['genre' => $genres]);
        });
    }

    private function removeGenreFromPieces(string $name): void
    {
        Piece::whereJsonContains('genre', $name)->each(function (Piece $piece) use ($name) {
            $genres = array_values(array_filter($piece->genre ?? [], fn (string $g) => $g !== $name));
            $piece->update(['genre' => $genres]);
        });
    }
}
