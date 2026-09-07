<?php

namespace App\Support;

use App\Models\Event;
use App\Models\EventRegistration;
use Illuminate\Support\Facades\Storage;

final class BadgeDesign
{
    public const LABELS = ['company' => 'Company name', 'event' => 'Event title', 'meta' => 'Date and location', 'name' => 'Attendee name', 'category' => 'Category', 'member' => 'Member ID', 'room' => 'Room assignment', 'qr' => 'QR code', 'company_logo' => 'Company logo', 'event_logo' => 'Event logo'];

    public static function defaults(string $layout = 'standard'): array
    {
        $positions = [
            'company' => [22, 7, 70, 9, 12], 'event' => [7, 22, 86, 12, 17],
            'meta' => [7, 35, 86, 9, 9], 'name' => [7, 49, 86, 17, 25],
            'category' => [7, 68, 53, 7, 11], 'member' => [7, 81, 51, 9, 11],
            'room' => [7, 92, 86, 5, 8], 'qr' => [64, 75, 29, 21, 10],
            'company_logo' => [7, 6, 12, 10, 10], 'event_logo' => [78, 7, 14, 10, 10],
        ];
        if (in_array($layout, ['image_header', 'split'])) {
            $positions['company'] = [7, 23, 86, 7, 12];
            $positions['event'] = [7, 31, 86, 10, 15];
            $positions['meta'] = [7, 42, 86, 6, 8];
        }

        return collect($positions)->map(fn ($p, $key) => ['x' => $p[0], 'y' => $p[1], 'w' => $p[2], 'h' => $p[3], 'size' => $p[4], 'align' => 'left', 'visible' => $key !== 'event_logo'])->all();
    }

    public static function fields(Event $event): array
    {
        return array_replace_recursive(self::defaults($event->badge_layout ?? 'standard'), $event->badge_fields ?? []);
    }

    public static function dimensions(Event $event): array
    {
        return $event->badge_size === 'A5' ? [148, 210] : [105, 148];
    }

    public static function image(?string $path): ?string
    {
        if (! $path || ! Storage::disk('public')->exists($path)) {
            return null;
        }

        return 'data:'.Storage::disk('public')->mimeType($path).';base64,'.base64_encode(Storage::disk('public')->get($path));
    }

    public static function values(Event $event, ?EventRegistration $registration): array
    {
        $name = $registration?->participant->name ?? 'Alex Morgan';
        if ($event->badge_name_format === 'initials') {
            $parts = preg_split('/\s+/u', trim($name), -1, PREG_SPLIT_NO_EMPTY);
            if (count($parts) >= 3) {
                $name = implode(' ', [$parts[0], ...array_map(fn ($part) => mb_strtoupper(mb_substr($part, 0, 1)).'.', array_slice($parts, 1, -1)), end($parts)]);
            }
        }
        $assignment = $registration?->roomAssignment;

        return [
            'company' => $event->company?->name ?? 'Event Organizer', 'event' => $event->title,
            'meta' => $event->event_date->format('j M Y').($event->end_date && ! $event->end_date->equalTo($event->event_date) ? ' - '.$event->end_date->format('j M Y') : '').($event->location ? ' · '.$event->location : ''),
            'name' => $name, 'category' => $registration?->participant->category ?: 'Attendee',
            'member' => $registration?->participant->member_id ?: 'Event Pass',
            'room' => $event->accommodation_published && $assignment ? 'Room '.$assignment->room->name.' · '.$assignment->room->floor->block->name.', '.$assignment->room->floor->name : '',
        ];
    }
}
