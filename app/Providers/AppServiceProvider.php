<?php

namespace App\Providers;

use App\Auth\SoliAdminProvider;
use App\Support\PermissionMatrix;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use Laravel\Socialite\Contracts\Factory as SocialiteFactory;
use Laravel\Socialite\SocialiteManager;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureSocialite();
        $this->syncPermissions();
    }

    private function syncPermissions(): void
    {
        if (app()->runningUnitTests()) {
            return;
        }

        $hash = md5(json_encode([PermissionMatrix::PERMISSIONS, array_keys(PermissionMatrix::ROLE_DEFAULTS)]));

        if (Cache::get('permission_matrix_hash') === $hash) {
            return;
        }

        if (! Schema::hasTable(config('permission.table_names.permissions', 'soli_permissions'))) {
            return;
        }

        PermissionMatrix::sync();
        Cache::put('permission_matrix_hash', $hash);
    }

    private function configureSocialite(): void
    {
        /** @var SocialiteManager $socialite */
        $socialite = $this->app->make(SocialiteFactory::class);

        $socialite->extend('soli-admin', function () use ($socialite) {
            $config = config('services.soli_admin');

            return $socialite->buildProvider(SoliAdminProvider::class, $config);
        });
    }
}
