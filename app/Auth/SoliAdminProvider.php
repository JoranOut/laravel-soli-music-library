<?php

namespace App\Auth;

use Laravel\Socialite\Two\AbstractProvider;
use Laravel\Socialite\Two\User;

class SoliAdminProvider extends AbstractProvider
{
    protected $scopes = ['openid', 'profile', 'email', 'roles', 'assignments'];

    protected $scopeSeparator = ' ';

    protected function getAuthUrl($state): string
    {
        return $this->buildAuthUrlFromBase($this->baseUrl().'/oauth/authorize', $state);
    }

    protected function getTokenUrl(): string
    {
        return $this->serverUrl().'/oauth/token';
    }

    /**
     * @param  string  $token
     * @return array<string, mixed>
     */
    protected function getUserByToken($token): array
    {
        $response = $this->getHttpClient()->get($this->serverUrl().'/api/oauth/userinfo', [
            'headers' => [
                'Authorization' => 'Bearer '.$token,
                'Accept' => 'application/json',
            ],
        ]);

        return json_decode((string) $response->getBody(), true);
    }

    protected function mapUserToObject(array $user): User
    {
        return (new User)->setRaw($user)->map([
            'id' => $user['sub'],
            'name' => $user['preferred_username'] ?? $user['name'] ?? '',
            'email' => $user['email'] ?? '',
        ]);
    }

    /**
     * Base URL for browser-facing requests (redirects).
     * Uses SOLI_ADMIN_URL (e.g. http://localhost:8000).
     */
    private function baseUrl(): string
    {
        return rtrim(config('services.soli_admin.base_url'), '/');
    }

    /**
     * Server-to-server URL for token exchange and userinfo.
     * In Docker this may differ from base_url (e.g. host.docker.internal).
     */
    private function serverUrl(): string
    {
        return rtrim(config('services.soli_admin.server_url') ?: $this->baseUrl(), '/');
    }
}
