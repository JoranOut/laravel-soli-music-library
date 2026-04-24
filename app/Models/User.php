<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['oidc_sub', 'name', 'email', 'oidc_roles', 'last_synced_at'])]
#[Hidden(['remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory;

    use HasRoles;

    protected function casts(): array
    {
        return [
            'oidc_roles' => 'array',
            'last_synced_at' => 'datetime',
        ];
    }

    /** @return HasMany<DownloadLog, $this> */
    public function downloadLogs(): HasMany
    {
        return $this->hasMany(DownloadLog::class);
    }
}
