<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OnboardingTourController extends Controller
{
    public function complete(Request $request): JsonResponse
    {
        $request->user()->update(['onboarding_tour_completed_at' => now()]);

        return response()->json(['status' => 'ok']);
    }
}
