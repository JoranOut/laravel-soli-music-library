<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\InstrumentFamily;
use App\Models\InstrumentType;
use App\Models\Part;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class InstrumentTypeController extends Controller
{
    public function index(): Response
    {
        $instrumentTypes = InstrumentType::with('instrumentFamily')
            ->withCount('parts')
            ->orderBy('instrument_family_id')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $families = InstrumentFamily::orderBy('name')->get();

        return Inertia::render('admin/instrument-types', [
            'instrumentTypes' => $instrumentTypes,
            'families' => $families,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:instrument_types,name'],
            'instrument_family_id' => ['required', 'exists:instrument_families,id'],
            'sort_order' => ['nullable', 'integer'],
        ]);

        $validated['sort_order'] = $validated['sort_order'] ?? InstrumentType::max('sort_order') + 1;

        InstrumentType::create($validated);

        return back();
    }

    public function update(Request $request, InstrumentType $instrumentType): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('instrument_types', 'name')->ignore($instrumentType->id)],
            'instrument_family_id' => ['required', 'exists:instrument_families,id'],
            'sort_order' => ['nullable', 'integer'],
        ]);

        $instrumentType->update($validated);

        return back();
    }

    public function destroy(Request $request, InstrumentType $instrumentType): RedirectResponse
    {
        $request->validate([
            'replace_with_id' => ['nullable', 'integer', 'exists:instrument_types,id'],
        ]);

        $replacementId = $request->input('replace_with_id');

        if ($replacementId) {
            Part::where('instrument_type_id', $instrumentType->id)
                ->update(['instrument_type_id' => $replacementId]);
        }

        $instrumentType->replaced_by_id = $replacementId;
        $instrumentType->save();
        $instrumentType->delete();

        return back();
    }

    public function storeFamily(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:instrument_families,name'],
        ]);

        InstrumentFamily::create($validated);

        return back();
    }

    public function updateFamily(Request $request, InstrumentFamily $instrumentFamily): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('instrument_families', 'name')->ignore($instrumentFamily->id)],
        ]);

        $instrumentFamily->update($validated);

        return back();
    }

    public function destroyFamily(InstrumentFamily $instrumentFamily): RedirectResponse
    {
        if ($instrumentFamily->instrumentTypes()->exists()) {
            return back()->withErrors(['family' => __('Cannot delete a family that has instrument types.')]);
        }

        $instrumentFamily->delete();

        return back();
    }
}
