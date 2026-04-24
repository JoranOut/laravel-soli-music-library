<?php

namespace App\Http\Controllers;

use App\Models\InstrumentType;
use App\Models\Orchestra;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(): Response
    {
        $assignments = collect(session('resolved_assignments', []));
        $orchestraIds = $assignments->pluck('orchestra_id')->unique();
        $orchestras = Orchestra::whereIn('id', $orchestraIds)->orderBy('sort_order')->get();

        $groups = $orchestras->map(function ($orchestra) use ($assignments) {
            $instrumentTypeIds = $assignments
                ->where('orchestra_id', $orchestra->id)
                ->pluck('instrument_type_id')
                ->unique()
                ->values()
                ->toArray();

            $instruments = InstrumentType::whereIn('id', $instrumentTypeIds)
                ->with('instrumentFamily')
                ->orderBy('sort_order')
                ->get();

            $pieces = $orchestra->pieces()
                ->whereHas('parts', fn ($q) => $q->whereIn('instrument_type_id', $instrumentTypeIds))
                ->with(['parts' => fn ($q) => $q->whereIn('instrument_type_id', $instrumentTypeIds)
                    ->with('instrumentType.instrumentFamily')])
                ->orderBy('title')
                ->get()
                ->map(fn ($piece) => [
                    'id' => $piece->id,
                    'title' => $piece->title,
                    'composer' => $piece->composer,
                    'parts' => $piece->parts->map(fn ($part) => $part->toArray())->values(),
                ])
                ->values();

            return [
                'orchestra' => $orchestra,
                'instruments' => $instruments,
                'pieces' => $pieces,
            ];
        })->values();

        return Inertia::render('Welcome', [
            'orchestraGroups' => $groups,
        ]);
    }
}
