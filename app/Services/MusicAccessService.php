<?php

namespace App\Services;

use App\Models\Part;
use App\Models\Piece;
use Illuminate\Support\Collection;

class MusicAccessService
{
    public function isEditor(): bool
    {
        return (bool) array_intersect(
            ['admin', 'muziekbeheer'],
            session('roles', [])
        );
    }

    public function isDirigent(): bool
    {
        return in_array('dirigent', session('roles', []));
    }

    /** @return array<int, array{orchestra_id: int, instrument_type_id: int}> */
    public function getResolvedAssignments(): array
    {
        return session('resolved_assignments', []);
    }

    /** @return int[] */
    public function getOrchestraIds(): array
    {
        return collect($this->getResolvedAssignments())
            ->pluck('orchestra_id')
            ->unique()
            ->values()
            ->toArray();
    }

    /** @return Collection<int, Part> */
    public function visibleParts(Piece $piece): Collection
    {
        $parts = $piece->parts()->with('instrumentType.instrumentFamily')->get();

        $user = auth()->user();

        if ($user?->can('download-all partijen')) {
            return $parts;
        }

        $visible = collect();

        if ($user?->can('download-score partijen')) {
            $visible = $parts->where('is_conductor', true);
        }

        if ($user?->can('download-assigned partijen')) {
            $pieceOrchestraIds = $piece->orchestras->pluck('id')->toArray();
            $assignments = $this->getResolvedAssignments();

            $allowedInstrumentTypeIds = collect($assignments)
                ->filter(fn ($a) => in_array($a['orchestra_id'], $pieceOrchestraIds))
                ->pluck('instrument_type_id')
                ->unique()
                ->toArray();

            $visible = $visible->merge(
                $parts->whereIn('instrument_type_id', $allowedInstrumentTypeIds)
            )->unique('id');
        }

        return $visible->values();
    }
}
