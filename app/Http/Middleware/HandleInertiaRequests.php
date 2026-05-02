<?php

namespace App\Http\Middleware;

use App\Models\Orchestra;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $user ? [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                ] : null,
                'roles' => $user ? session('roles', []) : [],
                'assignments' => $user ? session('assignments', []) : [],
                'resolved_assignments' => $user ? session('resolved_assignments', []) : [],
                'permissions' => $user ? $user->getAllPermissions()->pluck('name')->toArray() : [],
                'orchestras' => $user ? $this->getUserOrchestras() : [],
                'legal_agreement_accepted' => $user?->hasAcceptedLegalAgreement() ?? false,
            ],
            'adminUrl' => config('services.soli_admin.base_url', 'https://admin.soli.nl'),
            'sidebarOpen' => $request->cookie('sidebar_state', 'true') === 'true',
            'locale' => app()->getLocale(),
            'translations' => fn () => $this->getTranslations(),
        ];
    }

    /** @return array<int, array{id: int, name: string, abbreviation: string}> */
    private function getUserOrchestras(): array
    {
        $assignments = session('resolved_assignments', []);

        if (empty($assignments)) {
            return [];
        }

        $orchestraIds = collect($assignments)->pluck('orchestra_id')->unique()->toArray();

        return Orchestra::whereIn('id', $orchestraIds)
            ->orderBy('sort_order')
            ->get(['id', 'name', 'abbreviation'])
            ->toArray();
    }

    /**
     * @return array<string, string>
     */
    private function getTranslations(): array
    {
        $locale = app()->getLocale();

        if (app()->isProduction()) {
            return Cache::rememberForever("translations.{$locale}", fn () => $this->loadTranslations($locale));
        }

        return $this->loadTranslations($locale);
    }

    /**
     * @return array<string, string>
     */
    private function loadTranslations(string $locale): array
    {
        $path = lang_path("{$locale}.json");

        if (! file_exists($path)) {
            return [];
        }

        return json_decode(file_get_contents($path), true) ?? [];
    }
}
