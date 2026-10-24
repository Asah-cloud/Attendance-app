<?php

namespace App\Support;

use App\Models\Event;
use App\Models\RoomAssignment;

/**
 * Personalisation tokens a manager can type into a message, such as "Hello {first_name}".
 * Unknown tokens are left exactly as typed so a typo is visible rather than silently removed.
 * The compose panel mirrors this logic in JavaScript for its live preview; keep them in step.
 */
class MergeFields
{
    /** @var array<string, string> token => what it becomes */
    public const TOKENS = [
        'name' => 'Full name',
        'first_name' => 'First name',
        'event' => 'Event title',
        'organization' => 'Organization',
    ];

    /** Accommodation tokens: each recipient's own room for the event. */
    public const ROOM_TOKENS = [
        'room' => 'Room',
        'building' => 'Building',
        'floor' => 'Floor',
        'location' => 'Location and address',
        'room_link' => 'Link to their room details',
    ];

    /** What room tokens become for someone without a room yet. */
    public const NO_ROOM = 'not assigned yet';

    /** @return array<string, string> every token a message may use */
    public static function all(): array
    {
        return self::TOKENS + self::ROOM_TOKENS;
    }

    /** @return array<string, string> this person's room for the event, for the room tokens */
    public static function roomValues(?RoomAssignment $assignment): array
    {
        if (! $assignment) {
            return array_fill_keys(array_keys(self::ROOM_TOKENS), self::NO_ROOM);
        }
        $assignment->loadMissing(['room.floor.block.site', 'registration']);
        $room = $assignment->room;
        $site = $room->floor->block->site;

        return [
            'room' => $room->name,
            'building' => $room->floor->block->name,
            'floor' => $room->floor->name,
            'location' => collect([$site->name, $site->address])->filter()->join(', '),
            'room_link' => route('registrations.confirmation', $assignment->registration->management_token),
        ];
    }

    /** The current (assigned or checked-in) room of a person at an event, if any. */
    public static function assignmentFor(Event $event, ?int $participantId): ?RoomAssignment
    {
        if (! $participantId) {
            return null;
        }

        return RoomAssignment::query()
            ->whereIn('status', ['assigned', 'checked_in'])
            ->whereHas('registration', fn ($query) => $query->where('event_id', $event->id)->where('participant_id', $participantId))
            ->with(['room.floor.block.site', 'registration'])
            ->first();
    }

    /** Whether a text uses any room token, so sending it counts as telling someone their room. */
    public static function mentionsRoom(?string $text): bool
    {
        preg_match_all('/\{\s*([A-Za-z_]+)\s*\}/', (string) $text, $matches);

        return array_intersect(array_map('strtolower', $matches[1]), array_keys(self::ROOM_TOKENS)) !== [];
    }

    /** @return array<string, string> */
    public static function values(?string $name, ?string $eventTitle, ?string $organization): array
    {
        $name = trim((string) $name);
        $first = $name === '' ? 'there' : (preg_split('/\s+/u', $name)[0] ?? $name);

        return [
            'name' => $name === '' ? 'there' : $name,
            'first_name' => $first,
            'event' => (string) $eventTitle,
            'organization' => (string) $organization,
        ];
    }

    /** @param array<string, string> $values */
    public static function render(?string $text, array $values): string
    {
        return preg_replace_callback(
            '/\{\s*([A-Za-z_]+)\s*\}/',
            fn (array $match) => $values[strtolower($match[1])] ?? $match[0],
            (string) $text,
        ) ?? (string) $text;
    }

    /** @return list<string> tokens in the text that are not recognised, as typed */
    public static function unknown(?string $text): array
    {
        preg_match_all('/\{\s*([A-Za-z_]+)\s*\}/', (string) $text, $matches);

        return array_values(array_unique(array_filter(
            $matches[0],
            fn (string $token) => ! array_key_exists(strtolower(trim($token, '{} ')), self::all()),
        )));
    }
}
