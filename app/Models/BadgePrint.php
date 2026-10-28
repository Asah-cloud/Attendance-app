<?php

namespace App\Models;

use App\Support\BadgeDesign;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Collection;

/** A person's badge printed (or marked as printed) once, with the details that were on it. */
class BadgePrint extends Model
{
    /** Fields that differ from person to person; the rest (event title, logos...) are the same on every badge. */
    public const PERSONAL_FIELDS = ['name', 'department', 'category', 'group', 'country', 'member', 'room'];

    protected $fillable = ['event_id', 'event_registration_id', 'user_id', 'details', 'marked'];

    protected function casts(): array
    {
        return ['details' => 'array', 'marked' => 'boolean'];
    }

    public function registration(): BelongsTo
    {
        return $this->belongsTo(EventRegistration::class, 'event_registration_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Record that these badges were printed, keeping the personal details shown on each as they are now.
     * The event must already carry the design that was printed (staff settings applied for staff badges).
     *
     * @param  Collection<int, EventRegistration>  $registrations
     */
    public static function record(Event $event, Collection $registrations, ?int $userId, bool $marked = false): void
    {
        self::recordDetails($event->id, self::details($event, $registrations), $userId, $marked);
    }

    /**
     * The personal details shown on each of these badges, by registration ID, to record now or once
     * a large print is downloaded.
     *
     * @param  Collection<int, EventRegistration>  $registrations
     * @return array<int, array<string, string>>
     */
    public static function details(Event $event, Collection $registrations): array
    {
        $fields = BadgeDesign::fields($event);
        $shown = array_filter(self::PERSONAL_FIELDS, fn ($key) => $fields[$key]['visible'] ?? false);

        return $registrations->mapWithKeys(function (EventRegistration $registration) use ($event, $fields, $shown) {
            $values = BadgeDesign::values($event, $registration);
            $details = collect($shown)->mapWithKeys(fn ($key) => [BadgeDesign::LABELS[$key] => (string) $values[$key]])->all();
            // The same for everyone in one print, but often changed between prints (PARTICIPANT, HEALTH, MUSIC...).
            if (($fields['custom']['visible'] ?? false) && filled($fields['custom']['text'] ?? null)) {
                $details[BadgeDesign::LABELS['custom']] = $fields['custom']['text'];
            }

            return [$registration->id => $details];
        })->all();
    }

    /** @param  array<int, array<string, string>>  $details  From details(), by registration ID. */
    public static function recordDetails(int $eventId, array $details, ?int $userId, bool $marked = false): void
    {
        $now = now();
        $rows = collect($details)->map(fn ($printed, $registrationId) => ['event_id' => $eventId, 'event_registration_id' => $registrationId, 'user_id' => $userId,
            'details' => json_encode($printed), 'marked' => $marked, 'created_at' => $now, 'updated_at' => $now]);
        foreach ($rows->chunk(200) as $chunk) {
            self::insert($chunk->values()->all());
        }
    }
}
