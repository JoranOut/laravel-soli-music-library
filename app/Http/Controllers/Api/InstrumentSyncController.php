<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\InstrumentFamily;
use App\Models\InstrumentType;
use Illuminate\Http\JsonResponse;

class InstrumentSyncController extends Controller
{
    public function index(): JsonResponse
    {
        $replacements = InstrumentType::onlyTrashed()
            ->whereNotNull('replaced_by_id')
            ->with('replacedBy:id,name')
            ->get()
            ->map(fn (InstrumentType $type) => [
                'old_name' => $type->name,
                'new_name' => $type->replacedBy->name,
            ]);

        return response()->json([
            'families' => InstrumentFamily::select('id', 'name')->orderBy('name')->get(),
            'soorten' => InstrumentType::select('id', 'name', 'instrument_family_id')->orderBy('name')->get(),
            'replacements' => $replacements,
        ]);
    }
}
