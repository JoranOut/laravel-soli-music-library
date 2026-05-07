<?php

namespace App\Models;

use Database\Factories\InstrumentTypeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['external_id', 'name', 'instrument_family_id', 'sort_order', 'aliases', 'replaced_by_id'])]
class InstrumentType extends Model
{
    /** @use HasFactory<InstrumentTypeFactory> */
    use HasFactory, SoftDeletes;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['aliases' => 'array'];
    }

    /** @return BelongsTo<InstrumentFamily, $this> */
    public function instrumentFamily(): BelongsTo
    {
        return $this->belongsTo(InstrumentFamily::class);
    }

    /** @return BelongsTo<self, $this> */
    public function replacedBy(): BelongsTo
    {
        return $this->belongsTo(self::class, 'replaced_by_id');
    }

    /** @return HasMany<Part, $this> */
    public function parts(): HasMany
    {
        return $this->hasMany(Part::class);
    }
}
