<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class WebhookController extends Controller
{
    public function handle(Request $request): JsonResponse
    {
        $event = $request->input('event');

        match ($event) {
            'catalog.updated' => $this->handleCatalogUpdated(),
            'assignment.changed' => $this->handleAssignmentChanged($request),
            default => Log::info("Ignoring unknown webhook event: {$event}"),
        };

        return response()->json(['ok' => true]);
    }

    private function handleCatalogUpdated(): void
    {
        Artisan::call('music:sync-catalog');

        Log::info('Webhook: catalog synced');
    }

    private function handleAssignmentChanged(Request $request): void
    {
        $userId = $request->input('payload.user_id');

        if (! $userId) {
            Log::warning('Webhook assignment.changed missing user_id');

            return;
        }

        $user = User::where('oidc_sub', $userId)->first();

        if (! $user) {
            return;
        }

        DB::table('sessions')->where('user_id', $user->id)->delete();

        Log::info("Webhook: invalidated sessions for user {$user->id}");
    }
}
