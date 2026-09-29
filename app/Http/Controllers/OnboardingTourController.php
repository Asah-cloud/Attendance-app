<?php

namespace App\Http\Controllers;

use App\Support\OnboardingTours;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class OnboardingTourController extends Controller
{
    public function complete(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'tour' => ['required', 'string', Rule::in(OnboardingTours::KEYS)],
        ]);

        $user = $request->user();
        $seen = $user->onboarding_tours_seen ?? [];

        if (! in_array($validated['tour'], $seen, true)) {
            $seen[] = $validated['tour'];
            $user->update(['onboarding_tours_seen' => $seen]);
        }

        return response()->json(['status' => 'ok']);
    }
}
