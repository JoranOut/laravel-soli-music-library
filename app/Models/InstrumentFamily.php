<?php

namespace App\Models;

use Database\Factories\InstrumentFamilyFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['external_id', 'name'])]
class InstrumentFamily extends Model
{
    /** @use HasFactory<InstrumentFamilyFactory> */
    use HasFactory, SoftDeletes;

    /** @return HasMany<InstrumentType, $this> */
    public function instrumentTypes(): HasMany
    {
        return $this->hasMany(InstrumentType::class);
    }
}
