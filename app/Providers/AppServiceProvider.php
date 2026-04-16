<?php

namespace App\Providers;

use App\Auth\SoliAdminProvider;
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
