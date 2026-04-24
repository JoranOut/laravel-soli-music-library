<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\InstrumentType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class InstrumentAliasController extends Controller
{
    public function index(): Response
    {
        $instrumentTypes = InstrumentType::with('instrumentFamily')
            ->orderBy('instrument_family_id')
            ->orderBy('sort_order')
            ->get();

        return Inertia::render('admin/instrument-aliases', [
            'instrumentTypes' => $instrumentTypes,
        ]);
    }

    public function update(Request $request, InstrumentType $instrumentType): RedirectResponse
    {
        $validated = $request->validate([
            'aliases' => ['present', 'array'],
            'aliases.*' => ['string', 'max:255'],
        ]);

        $aliases = collect($validated['aliases'])
            ->map(fn (string $alias) => mb_strtolower(trim($alias)))
            ->filter(fn (string $alias) => $alias !== '')
            ->unique()
            ->values()
            ->all();

        $instrumentType->update(['aliases' => $aliases]);

        return back();
    }
}
