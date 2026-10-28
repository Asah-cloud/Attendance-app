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
        $fields = BadgeDesign::fields($event);
        $shown = array_filter(self::PERSONAL_FIELDS, fn ($key) => $fields[$key]['visible'] ?? false);
        $now = now();
        $rows = $registrations->map(function (EventRegistration $registration) use ($event, $shown, $userId, $marked, $now) {
            $values = BadgeDesign::values($event, $registration);
            $details = collect($shown)->mapWithKeys(fn ($key) => [BadgeDesign::LABELS[$key] => (string) $values[$key]])->all();

            return ['event_id' => $event->id, 'event_registration_id' => $registration->id, 'user_id' => $userId,
                'details' => json_encode($details), 'marked' => $marked, 'created_at' => $now, 'updated_at' => $now];
        });
        foreach ($rows->chunk(200) as $chunk) {
            self::insert($chunk->values()->all());
        }
    }
}
