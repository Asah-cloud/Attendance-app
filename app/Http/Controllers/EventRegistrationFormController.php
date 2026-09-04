<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\EventRegistrationField;
use App\Notifications\Concerns\NotifiesPerChannel;
use App\Notifications\EventRegistrationSubmitted;
use App\Services\ParticipantRegistrationService;
use App\Services\RegistrationLifecycleService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use SimpleSoftwareIO\QrCode\Facades\QrCode;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EventRegistrationFormController extends Controller
{
    public function registrations(Request $request, Event $event): View
    {
        $this->authorize('manageWhenOpen', $event);
        $event->ensureSystemRegistrationFields();
        $status = $request->string('status')->toString();
        $registrations = $event->registrations()
            ->with('participant')
            ->when($status, fn ($query) => $query->where('status', $status))
            ->latest('registered_at')
            ->paginate(25)
            ->withQueryString();
        $categoryField = $event->registrationFields()->where('field_key', 'category')->first();
        $genderField = $event->registrationFields()->where('field_key', 'gender')->first();

        return view('events.registrations', compact('event', 'registrations', 'status', 'categoryField', 'genderField'));
    }

    public function storeRegistration(Request $request, Event $event, ParticipantRegistrationService $participants): RedirectResponse
    {
        $this->authorize('manageWhenOpen', $event);
        $event->ensureSystemRegistrationFields();
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30', 'required_without:email'],
            'gender' => ['required', Rule::in($this->fieldOptions($event, 'gender', ['Male', 'Female']))],
            'category' => $this->categoryRule($event),
        ]);

        $registration = DB::transaction(function () use ($event, $validated, $participants): EventRegistration {
            $event = Event::query()->lockForUpdate()->findOrFail($event->id);
            $participant = $participants->resolveParticipant($event, $validated);
            if ($event->registrations()->where('participant_id', $participant->id)->exists()) {
                throw ValidationException::withMessages(['email' => 'This person already has a registration for this event.']);
            }

            $atCapacity = $event->registration_capacity !== null
                && $event->registrations()->where('status', EventRegistration::STATUS_CONFIRMED)->count() >= $event->registration_capacity;

            return $event->registrations()->create([
                'participant_id' => $participant->id,
                'status' => $atCapacity ? EventRegistration::STATUS_WAITLISTED : EventRegistration::STATUS_CONFIRMED,
                'approved_at' => $atCapacity ? null : now(),
                'source' => 'manual',
            ]);
        });

        $this->sendConfirmation($registration);

        return back()->with('success', 'Attendee registered successfully.');
    }

    public function approve(Event $event, EventRegistration $registration, RegistrationLifecycleService $lifecycle): RedirectResponse
    {
        $this->authorizeRegistration($event, $registration);
        $lifecycle->approve($registration);

        return back()->with('success', 'Registration approved.');
    }

    public function reject(Event $event, EventRegistration $registration, RegistrationLifecycleService $lifecycle): RedirectResponse
    {
        $this->authorizeRegistration($event, $registration);
        $lifecycle->reject($registration);

        return back()->with('success', 'Registration rejected.');
    }

    public function cancelRegistration(Event $event, EventRegistration $registration, RegistrationLifecycleService $lifecycle): RedirectResponse
    {
        $this->authorizeRegistration($event, $registration);
        $lifecycle->cancel($registration);

        return back()->with('success', 'Registration cancelled.');
    }

    public function destroyAll(Request $request, Event $event): RedirectResponse
    {
        $this->authorize('update', $event);

        if (trim((string) $request->input('confirm_title')) !== $event->title) {
            throw ValidationException::withMessages(['confirm_title' => 'Type the exact event title to confirm.']);
        }

        // Registrations whose participant has no attendance recorded for this event.
        $deletable = fn () => $event->registrations()->whereNotExists(fn ($query) => $query
            ->selectRaw('1')
            ->from('attendances')
            ->whereColumn('attendances.participant_id', 'event_registrations.participant_id')
            ->where('attendances.event_id', $event->id));

        $total = $event->registrations()->count();
        $removed = $deletable()->count();
        $deletable()->delete();
        $kept = $total - $removed;

        $message = "{$removed} attendee(s) removed.";
        if ($kept > 0) {
            $message .= " {$kept} kept because they have already checked in.";
        }

        return redirect()->route('events.registrations.index', $event)->with('success', $message);
    }

    public function updateParticipant(Request $request, Event $event, EventRegistration $registration): RedirectResponse
    {
        $this->authorizeRegistration($event, $registration);
        $event->ensureSystemRegistrationFields();
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30', 'required_without:email'],
            'gender' => ['required', Rule::in($this->fieldOptions($event, 'gender', ['Male', 'Female']))],
            'category' => $this->categoryRule($event),
            'member_id' => ['nullable', 'string', 'max:255'],
            'dietary_notes' => ['nullable', 'string', 'max:500'],
        ]);
        $participant = $registration->participant;
        $changes = collect($validated)
            ->filter(fn ($value, $field) => (string) $participant->getAttribute($field) !== (string) $value)
            ->mapWithKeys(fn ($value, $field) => [$field => ['old' => $participant->getAttribute($field), 'new' => $value]])
            ->all();

        $participant->update($validated);

        if ($changes !== []) {
            $participant->auditLogs()->create([
                'user_id' => $request->user()->id,
                'changes' => $changes,
            ]);
        }

        return back()->with('success', 'Attendee details updated.');
    }

    public function participantHistory(Event $event, EventRegistration $registration): View
    {
        $this->authorizeRegistration($event, $registration);
        $logs = $registration->participant->auditLogs()->with('user')->get();

        return view('events.participant-history', compact('event', 'registration', 'logs'));
    }

    public function badges(Event $event): View
    {
        $this->authorize('manageWhenOpen', $event);
        $event->loadMissing('company');
        $registrations = $event->registrations()
            ->where('status', EventRegistration::STATUS_CONFIRMED)
            ->with(['participant', 'roomAssignment.room.floor.block'])
            ->get();

        $categories = $registrations->pluck('participant.category')->filter()->unique()->sort()->values();
        $palette = ['#7C3AED', '#0F766E', '#B45309', '#BE123C', '#1D4ED8', '#4338CA'];
        $categoryColors = $categories->mapWithKeys(fn ($category, $index) => [
            $category => $event->badge_category_colors[$category] ?? $palette[$index % count($palette)],
        ]);

        return view('events.badges', compact('event', 'registrations', 'categories', 'categoryColors'));
    }

    public function badgesPdf(Event $event): Response
    {
        $this->authorize('manageWhenOpen', $event);
        set_time_limit(300);
        $event->loadMissing('company');
        $registrations = $event->registrations()
            ->where('status', EventRegistration::STATUS_CONFIRMED)
            ->with(['participant', 'roomAssignment.room.floor.block'])
            ->get();

        $categories = $registrations->pluck('participant.category')->filter()->unique()->sort()->values();
        $palette = ['#7C3AED', '#0F766E', '#B45309', '#BE123C', '#1D4ED8', '#4338CA'];
        $categoryColors = $categories->mapWithKeys(fn ($category, $index) => [
            $category => $event->badge_category_colors[$category] ?? $palette[$index % count($palette)],
        ]);

        $localFilePath = function (?string $absolutePath): ?string {
            return $absolutePath && is_file($absolutePath) ? str_replace('\\', '/', $absolutePath) : null;
        };

        $backgroundImage = $localFilePath(public_path('images/badges/professional-teal-background-v1.png'));
        $badgeImage = $event->badge_image_path ? $localFilePath(Storage::disk('public')->path($event->badge_image_path)) : null;
        $companyLogo = $event->company?->logo_path ? $localFilePath(Storage::disk('public')->path($event->company->logo_path)) : null;
        $eventLogo = $event->logo_path ? $localFilePath(Storage::disk('public')->path($event->logo_path)) : null;

        $pdf = Pdf::loadView('events.badges-pdf', compact(
            'event', 'registrations', 'categoryColors', 'backgroundImage', 'badgeImage', 'companyLogo', 'eventLogo'
        ))->setPaper($event->badge_size === 'A5' ? 'a5' : 'a6', 'portrait');

        return $pdf->download('badges-'.$event->slug.'.pdf');
    }

    public function updateBadgeSettings(Request $request, Event $event): RedirectResponse
    {
        $this->authorize('manageWhenOpen', $event);
        $validated = $request->validate([
            'badge_size' => ['required', 'in:A5,A6'],
            'badge_design' => ['required', 'in:default,category'],
            'badge_layout' => ['sometimes', 'in:standard,image_header,split'],
            'badge_image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'remove_badge_image' => ['nullable', 'boolean'],
            'badge_primary_color' => ['sometimes', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'badge_accent_color' => ['sometimes', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'badge_image_position_x' => ['sometimes', 'integer', 'between:0,100'],
            'badge_image_position_y' => ['sometimes', 'integer', 'between:0,100'],
            'categories' => ['nullable', 'array'],
            'categories.*' => ['nullable', 'string', 'max:255'],
            'colors' => ['nullable', 'array'],
            'colors.*' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
        ]);

        $available = $event->registrations()->where('status', EventRegistration::STATUS_CONFIRMED)
            ->with('participant:id,category')->get()->pluck('participant.category')->filter()->unique();
        $colors = [];
        foreach ($validated['categories'] ?? [] as $index => $category) {
            if ($available->contains($category) && isset($validated['colors'][$index])) {
                $colors[$category] = strtoupper($validated['colors'][$index]);
            }
        }

        $layout = $validated['badge_layout'] ?? $event->badge_layout ?? 'standard';
        $removingImage = $request->boolean('remove_badge_image');
        if ($layout !== 'standard'
            && ! $request->hasFile('badge_image')
            && ($removingImage || ! $event->badge_image_path)) {
            throw ValidationException::withMessages([
                'badge_image' => 'Upload an image before using a customized badge layout.',
            ]);
        }

        if ($removingImage && $event->badge_image_path) {
            Storage::disk('public')->delete($event->badge_image_path);
            $event->badge_image_path = null;
        }

        if ($request->hasFile('badge_image')) {
            if ($event->badge_image_path) {
                Storage::disk('public')->delete($event->badge_image_path);
            }
            $event->badge_image_path = $request->file('badge_image')->store('badge-images', 'public');
        }

        $event->fill([
            'badge_size' => $validated['badge_size'],
            'badge_design' => $validated['badge_design'],
            'badge_category_colors' => $colors,
            'badge_layout' => $layout,
            'badge_primary_color' => strtoupper($validated['badge_primary_color'] ?? $event->badge_primary_color ?? '#0F766E'),
            'badge_accent_color' => strtoupper($validated['badge_accent_color'] ?? $event->badge_accent_color ?? '#0F172A'),
            'badge_image_position_x' => $validated['badge_image_position_x'] ?? $event->badge_image_position_x ?? 50,
            'badge_image_position_y' => $validated['badge_image_position_y'] ?? $event->badge_image_position_y ?? 50,
        ])->save();

        return back()->with('success', 'Badge design saved. The preview is ready to print.');
    }

    public function resend(Event $event, EventRegistration $registration): RedirectResponse
    {
        $this->authorizeRegistration($event, $registration);
        if (! $registration->participant->email) {
            return back()->withErrors(['registration' => 'This attendee does not have an email address.']);
        }
        $this->sendConfirmation($registration);

        return back()->with('success', 'Confirmation sent again.');
    }

    public function export(Event $event): StreamedResponse
    {
        $this->authorize('manageWhenOpen', $event);
        $filename = Str::slug($event->title).'-registrations.csv';

        return response()->streamDownload(function () use ($event): void {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Name', 'Email', 'Phone', 'Category', 'Gender', 'Status', 'Source', 'Registered At', 'Registration Code']);
            $event->registrations()->with('participant')->latest('registered_at')->chunk(250, function ($registrations) use ($handle): void {
                foreach ($registrations as $registration) {
                    fputcsv($handle, [$registration->participant->name, $registration->participant->email, $registration->participant->phone, $registration->participant->category, $registration->participant->gender, $registration->status, $registration->source, $registration->registered_at?->toDateTimeString(), $registration->registration_code]);
                }
            });
            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    public function edit(Event $event): View
    {
        $this->authorize('manageWhenOpen', $event);
        $event->ensureSystemRegistrationFields();

        return view('events.registration-form', [
            'event' => $event->fresh('registrationFields'),
            'fieldTypes' => EventRegistrationField::CUSTOM_TYPES,
        ]);
    }

    public function updateSettings(Request $request, Event $event, RegistrationLifecycleService $lifecycle): RedirectResponse
    {
        $this->authorize('manageWhenOpen', $event);
        $validated = $request->validate([
            'registration_enabled' => ['required', 'boolean'],
            'registration_opens_at' => ['nullable', 'date'],
            'registration_closes_at' => ['nullable', 'date', 'after:registration_opens_at'],
            'registration_capacity' => ['nullable', 'integer', 'min:1'],
            'registration_requires_approval' => ['required', 'boolean'],
            'registration_terms' => ['required', 'string', 'max:5000'],
            'registration_terms_version' => ['required', 'string', 'max:50'],
        ]);
        $oldCapacity = $event->registration_capacity;
        $event->update($validated);
        if ($validated['registration_capacity'] !== $oldCapacity) {
            $lifecycle->capacityChanged($event);
        }

        return back()->with('success', 'Registration settings updated.');
    }

    public function updateSystemField(Request $request, Event $event, EventRegistrationField $field): RedirectResponse
    {
        $this->authorize('manageWhenOpen', $event);
        abort_unless($field->event_id === $event->id && $field->is_system, 404);

        $isCategory = $field->field_key === 'category';
        $rules = ['label' => ['required', 'string', 'max:255']];
        if ($isCategory) {
            $rules['field_type'] = ['required', Rule::in(['text', 'select'])];
            $rules['options'] = ['nullable', 'string', 'max:2000'];
        }
        $validated = $request->validate($rules);

        $update = ['label' => $validated['label'], 'is_required' => true, 'is_active' => true];
        if ($isCategory) {
            $options = $this->options($validated['options'] ?? null);
            if ($validated['field_type'] === 'select' && count($options) < 2) {
                return back()->withErrors(['options' => 'A dropdown category needs at least two options.'])->withInput();
            }
            $update['field_type'] = $validated['field_type'];
            $update['options'] = $validated['field_type'] === 'select' ? $options : null;
        }
        $field->update($update);

        return back()->with('success', 'System field updated.');
    }

    public function storeField(Request $request, Event $event): RedirectResponse
    {
        $this->authorize('manageWhenOpen', $event);
        $validated = $request->validate([
            'label' => ['required', 'string', 'max:255'],
            'field_type' => ['required', Rule::in(EventRegistrationField::CUSTOM_TYPES)],
            'is_required' => ['nullable', 'boolean'],
            'options' => ['nullable', 'string', 'max:2000'],
        ]);
        $options = $this->options($validated['options'] ?? null);

        if (in_array($validated['field_type'], ['select', 'radio'], true) && count($options) < 2) {
            return back()->withErrors(['options' => 'Select and radio fields require at least two options.'])->withInput();
        }

        $event->registrationFields()->create([
            'field_key' => 'custom_'.Str::uuid(),
            'label' => $validated['label'],
            'field_type' => $validated['field_type'],
            'is_system' => false,
            'is_required' => $request->boolean('is_required'),
            'options' => $options ?: null,
            'display_order' => ((int) $event->registrationFields()->max('display_order')) + 10,
            'is_active' => true,
        ]);

        return back()->with('success', 'Custom field added.');
    }

    public function destroyField(Event $event, EventRegistrationField $field): RedirectResponse
    {
        $this->authorize('manageWhenOpen', $event);
        abort_unless($field->event_id === $event->id, 404);
        abort_if($field->is_system, 422, 'System fields cannot be removed.');
        $field->delete();

        return back()->with('success', 'Custom field removed.');
    }

    public function printQr(Event $event): View
    {
        $this->authorize('manageWhenOpen', $event);

        return view('events.registration-qr', compact('event'));
    }

    public function downloadQr(Event $event): Response
    {
        $this->authorize('manageWhenOpen', $event);
        $svg = QrCode::format('svg')->size(1000)->generate(route('events.register', $event));
        $filename = Str::slug($event->title).'-registration-qr.svg';

        return response($svg, 200, [
            'Content-Type' => 'image/svg+xml',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    private function fieldOptions(Event $event, string $fieldKey, array $default): array
    {
        $field = $event->registrationFields()->where('field_key', $fieldKey)->first();

        return $field?->options ?: $default;
    }

    private function categoryRule(Event $event): array
    {
        $field = $event->registrationFields()->where('field_key', 'category')->first();

        return $field && $field->field_type === 'select'
            ? ['required', Rule::in($field->options ?? [])]
            : ['required', 'string', 'max:100'];
    }

    private function options(?string $options): array
    {
        return collect(preg_split('/\r\n|\r|\n/', $options ?? ''))
            ->map(fn ($option) => trim($option))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function authorizeRegistration(Event $event, EventRegistration $registration): void
    {
        $this->authorize('manageWhenOpen', $event);
        abort_unless($registration->event_id === $event->id, 404);
    }

    private function sendConfirmation(EventRegistration $registration): void
    {
        $registration->loadMissing(['event', 'participant']);
        if ($registration->participant->email || $registration->participant->phone) {
            NotifiesPerChannel::send($registration->participant, new EventRegistrationSubmitted($registration));
        }
    }
}
