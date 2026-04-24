<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\InstrumentType;
use App\Models\Orchestra;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Laravel\Socialite\Facades\Socialite;
use Spatie\Permission\Models\Role;
use Symfony\Component\HttpFoundation\Response;

class AuthController extends Controller
{
    public function redirect(): RedirectResponse
    {
        return Socialite::driver('soli-admin')->redirect();
    }

    public function callback(): RedirectResponse
    {
        $socialiteUser = Socialite::driver('soli-admin')->user();

        $user = User::updateOrCreate(
            ['oidc_sub' => $socialiteUser->getId()],
            [
                'name' => $socialiteUser->getName(),
                'email' => $socialiteUser->getEmail(),
            ]
        );

        $raw = $socialiteUser->getRaw();

        $roles = $raw['roles'] ?? [];

        $resolvedAssignments = collect($raw['assignments'] ?? [])->map(function ($a) {
            $orchestra = Orchestra::where('external_id', $a['onderdeel_id'])->first();
            $instrumentType = InstrumentType::where('external_id', $a['instrument_soort_id'])->first();

            return [
                'orchestra_id' => $orchestra?->id,
                'instrument_type_id' => $instrumentType?->id,
            ];
        })->filter(fn ($a) => $a['orchestra_id'] && $a['instrument_type_id'])->values()->toArray();

        session([
            'roles' => $roles,
            'assignments' => $raw['assignments'] ?? [],
            'resolved_assignments' => $resolvedAssignments,
        ]);

        $knownRoles = Role::whereIn('name', $roles)->pluck('name')->toArray();
        $user->syncRoles($knownRoles);

        auth()->login($user);

        return redirect()->intended('/');
    }

    public function logout(Request $request): Response
    {
        auth()->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        $adminUrl = config('services.soli_admin.base_url', 'https://admin.soli.nl');
        $redirectUri = urlencode(config('app.url'));

        return Inertia::location("{$adminUrl}/oauth/logout?redirect_uri={$redirectUri}");
    }
}
