<?php

namespace App\Models;

use Database\Factories\OrchestraFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['external_id', 'name', 'abbreviation', 'type', 'is_active', 'sort_order'])]
class Orchestra extends Model
{
    /** @use HasFactory<OrchestraFactory> */
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /** @return BelongsToMany<Piece, $this> */
    public function pieces(): BelongsToMany
    {
        return $this->belongsToMany(Piece::class, 'piece_orchestra')->wherePivotNull('tot');
    }

    /** @return HasMany<PieceOrchestra, $this> */
    public function pieceUsages(): HasMany
    {
        return $this->hasMany(PieceOrchestra::class);
    }
}
