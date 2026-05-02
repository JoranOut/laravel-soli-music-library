<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['oidc_sub', 'name', 'email', 'oidc_roles', 'oidc_assignments', 'last_synced_at'])]
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
            'oidc_assignments' => 'array',
            'last_synced_at' => 'datetime',
            'legal_agreement_accepted_at' => 'datetime',
        ];
    }

    public function hasAcceptedLegalAgreement(): bool
    {
        return $this->legal_agreement_accepted_at !== null;
    }

    /** @return HasMany<DownloadLog, $this> */
    public function downloadLogs(): HasMany
    {
        return $this->hasMany(DownloadLog::class);
    }
}
