<?php

namespace App\Http\Controllers;

use App\Models\PushSubscription;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/** The browser telling us it will (or will no longer) show this user's push notifications. */
class PushSubscriptionController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'endpoint' => ['required', 'string', 'url:https', 'max:2000'],
            'keys.p256dh' => ['required', 'string', 'max:255'],
            'keys.auth' => ['required', 'string', 'max:255'],
        ]);

        // A shared phone belongs to whoever turned notifications on last.
        PushSubscription::updateOrCreate(['endpoint_hash' => PushSubscription::hashFor($data['endpoint'])], [
            'user_id' => $request->user()->id,
            'endpoint' => $data['endpoint'],
            'public_key' => $data['keys']['p256dh'],
            'auth_token' => $data['keys']['auth'],
            'device' => Str::limit((string) $request->userAgent(), 117),
        ]);

        return response()->json(['subscribed' => true]);
    }

    public function destroy(Request $request): JsonResponse
    {
        $endpoint = (string) $request->validate(['endpoint' => ['required', 'string', 'max:2000']])['endpoint'];

        PushSubscription::query()->where('user_id', $request->user()->id)
            ->where('endpoint_hash', PushSubscription::hashFor($endpoint))->delete();

        return response()->json(['subscribed' => false]);
    }
}
