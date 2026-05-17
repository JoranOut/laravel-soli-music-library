<?php

namespace App\Models;

use Database\Factories\PieceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['title', 'composer', 'arranger', 'publisher', 'difficulty', 'notes', 'bought_for', 'bought_for_occasion', 'buy_date', 'genre', 'music_type', 'archive_number', 'status', 'audio_youtube_url', 'audio_file_path'])]
class Piece extends Model
{
    /** @use HasFactory<PieceFactory> */
    use HasFactory;

    use SoftDeletes;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'genre' => 'array',
            'buy_date' => 'date',
        ];
    }

    /** @return BelongsToMany<Orchestra, $this> */
    public function orchestras(): BelongsToMany
    {
        return $this->belongsToMany(Orchestra::class, 'speelperiodes')
            ->where(function ($query) {
                $query->whereNull('speelperiodes.van')
                    ->orWhere('speelperiodes.van', '<=', now()->toDateString());
            })
            ->where(function ($query) {
                $query->whereNull('speelperiodes.tot')
                    ->orWhere('speelperiodes.tot', '>=', now()->toDateString());
            });
    }

    /** @return HasMany<Speelperiode, $this> */
    public function speelperiodes(): HasMany
    {
        return $this->hasMany(Speelperiode::class);
    }

    /** @return HasMany<Part, $this> */
    public function parts(): HasMany
    {
        return $this->hasMany(Part::class);
    }
}
