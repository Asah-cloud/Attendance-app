<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\LoginActivity;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Platform admins' view of who signed in: the bell, its pop-ups, and the full list. */
class SignInActivityController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'company' => ['nullable', 'integer'],
            'role' => ['nullable', 'string', 'max:50'],
            'status' => ['nullable', 'in:successful,failed'],
        ]);

        $activities = LoginActivity::with(['user', 'company'])
            ->when($filters['search'] ?? null, fn ($q, $search) => $q->where(fn ($q) => $q
                ->where('email', 'like', '%'.strtolower($search).'%')
                ->orWhereHas('user', fn ($q) => $q->where('name', 'like', '%'.$search.'%'))))
            ->when($filters['company'] ?? null, fn ($q, $company) => $q->where('company_id', $company))
            ->when($filters['role'] ?? null, fn ($q, $role) => $q->where('roles', 'like', '%'.$role.'%'))
            ->when(($filters['status'] ?? null) === 'successful', fn ($q) => $q->where('successful', true))
            ->when(($filters['status'] ?? null) === 'failed', fn ($q) => $q->where('successful', false))
            ->latest('id')->paginate(50)->withQueryString();

        $request->user()->forceFill(['login_activity_seen_at' => now()])->save();

        return view('admin.sign-ins', [
            'activities' => $activities,
            'companies' => Company::orderBy('name')->get(['id', 'name']),
            'filters' => $filters,
        ]);
    }

    /**
     * What the bell needs, polled by the page: the unread count, the latest entries, and anything
     * newer than the last entry this page has already seen (shown as pop-ups).
     */
    public function feed(Request $request): JsonResponse
    {
        $admin = $request->user();
        $after = (int) $request->query('after', 0);
        $bell = fn () => LoginActivity::forBell($admin);

        $latest = $bell()->with(['user', 'company'])->latest('id')->limit(20)->get();

        return response()->json([
            'unread' => $bell()->when($admin->login_activity_seen_at, fn ($q, $seen) => $q->where('created_at', '>', $seen))->count(),
            'last_id' => (int) ($latest->first()?->id ?? $after),
            'items' => $latest->map(fn (LoginActivity $activity) => $this->item($activity))->values(),
            'new' => $after > 0
                ? $latest->where('id', '>', $after)->sortBy('id')->map(fn (LoginActivity $activity) => $this->item($activity))->values()
                : [],
        ]);
    }

    public function markSeen(Request $request): JsonResponse
    {
        $request->user()->forceFill(['login_activity_seen_at' => now()])->save();

        return response()->json(['unread' => 0]);
    }

    /** @return array<string, mixed> */
    private function item(LoginActivity $activity): array
    {
        return [
            'id' => $activity->id,
            'alert' => ! $activity->successful,
            'summary' => $activity->summary(),
            'time' => $activity->created_at->timezone(config('app.timezone'))->format('M j, g:i A'),
            'ago' => $activity->created_at->diffForHumans(),
        ];
    }
}
