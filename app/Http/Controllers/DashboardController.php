<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\Company;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\Participant;
use App\Models\SubscriptionPayment;
use App\Services\ApplicationCache;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(private readonly ApplicationCache $cache) {}

    public function __invoke(): View
    {
        $user = request()->user();

        if ($user->hasRole('admin')) {
            return $this->adminDashboard();
        }

        if ($user->hasRole('manager')) {
            return $this->managerDashboard($user->company_id);
        }

        $events = $user->events()->withCount(['confirmedParticipants', 'attendances'])
            ->orderBy('event_date')->get();

        return view('dashboard', [
            'dashboardType' => 'staff',
            'events' => $events,
        ]);
    }

    private function adminDashboard(): View
    {
        $today = now()->toDateString();
        $data = $this->cache->rememberAdminDashboard('dashboard:'.$today, function () use ($today): array {
            return [
                'stats' => [
                    'companies' => Company::count(),
                    'activeSubscriptions' => Company::where('is_active', true)
                        ->where(fn ($query) => $query->whereNull('subscription_ends_at')->orWhereDate('subscription_ends_at', '>=', $today))
                        ->count(),
                    'events' => Event::whereHas('company')->count(),
                    'participants' => Participant::whereHas('company')->count(),
                    'checkInsToday' => Attendance::whereDate('created_at', $today)->whereHas('event.company')->count(),
                    'revenueMinor' => SubscriptionPayment::where('status', 'paid')->whereHas('company')->sum('amount_minor'),
                ],
                'recentCompanies' => Company::withCount(['events', 'users'])->latest()->limit(5)->get(),
                'recentPayments' => SubscriptionPayment::with('company')->where('status', 'paid')->whereHas('company')->latest('paid_at')->limit(5)->get(),
                'upcomingEvents' => Event::with('company')->withCount('registrations')->whereHas('company')
                    ->whereNull('cancelled_at')->whereDate('event_date', '>=', $today)
                    ->orderBy('event_date')->limit(5)->get(),
                'expiringCompanies' => Company::whereNotNull('subscription_ends_at')
                    ->whereBetween('subscription_ends_at', [$today, now()->addDays(14)->toDateString()])
                    ->orderBy('subscription_ends_at')->limit(5)->get(),
            ];
        });

        return view('dashboard', [
            'dashboardType' => 'admin',
        ] + $data);
    }

    private function managerDashboard(?int $companyId): View
    {
        abort_unless($companyId, 403, 'Your account is not linked to a company.');
        $today = now()->toDateString();
        $data = $this->cache->rememberCompany($companyId, 'dashboard:'.$today, function () use ($companyId, $today): array {
            $eventIds = Event::where('company_id', $companyId)->select('id');

            return [
                'company' => Company::findOrFail($companyId),
                'stats' => [
                    'events' => Event::where('company_id', $companyId)->count(),
                    'upcomingEvents' => Event::where('company_id', $companyId)->whereNull('cancelled_at')
                        ->whereDate('event_date', '>=', $today)->count(),
                    'participants' => Participant::where('company_id', $companyId)->count(),
                    'registrations' => EventRegistration::whereIn('event_id', clone $eventIds)->count(),
                    'checkInsToday' => Attendance::whereIn('event_id', clone $eventIds)->whereDate('created_at', $today)->count(),
                    'pending' => EventRegistration::whereIn('event_id', clone $eventIds)
                        ->where('status', EventRegistration::STATUS_PENDING)->count(),
                ],
                'registrationStatuses' => EventRegistration::whereIn('event_id', clone $eventIds)
                    ->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status'),
                'categoryBreakdown' => Attendance::whereIn('event_id', clone $eventIds)
                    ->join('participants', 'participants.id', '=', 'attendances.participant_id')
                    ->selectRaw("COALESCE(NULLIF(participants.category, ''), 'Unspecified') as label, count(*) as total")
                    ->groupBy('label')->orderByDesc('total')->pluck('total', 'label'),
                'genderBreakdown' => Attendance::whereIn('event_id', clone $eventIds)
                    ->join('participants', 'participants.id', '=', 'attendances.participant_id')
                    ->selectRaw("COALESCE(NULLIF(participants.gender, ''), 'Unspecified') as label, count(*) as total")
                    ->groupBy('label')->orderByDesc('total')->pluck('total', 'label'),
                'upcomingEvents' => Event::where('company_id', $companyId)->withCount(['registrations', 'attendances'])
                    ->whereNull('cancelled_at')->whereDate('event_date', '>=', $today)
                    ->orderBy('event_date')->limit(5)->get(),
                'recentRegistrations' => EventRegistration::whereIn('event_id', clone $eventIds)
                    ->with(['participant', 'event'])->latest('registered_at')->limit(6)->get(),
            ];
        });

        return view('dashboard', [
            'dashboardType' => 'manager',
        ] + $data);
    }
}
