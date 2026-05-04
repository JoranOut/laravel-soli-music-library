<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['piece_id', 'orchestra_id', 'van', 'tot', 'details'])]
class Speelperiode extends Model
{
    protected $table = 'speelperiodes';

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'van' => 'date',
            'tot' => 'date',
        ];
    }

    /** @return BelongsTo<Piece, $this> */
    public function piece(): BelongsTo
    {
        return $this->belongsTo(Piece::class)->withTrashed();
    }

    /** @return BelongsTo<Orchestra, $this> */
    public function orchestra(): BelongsTo
    {
        return $this->belongsTo(Orchestra::class)->withTrashed();
    }
}
