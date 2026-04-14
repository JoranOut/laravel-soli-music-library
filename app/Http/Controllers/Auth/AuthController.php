<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Laravel\Socialite\Facades\Socialite;

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

        session([
            'roles' => $raw['roles'] ?? [],
            'assignments' => $raw['assignments'] ?? [],
        ]);

        auth()->login($user, remember: true);

        return redirect()->intended('/');
    }

    public function logout(Request $request): RedirectResponse
    {
        auth()->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}
