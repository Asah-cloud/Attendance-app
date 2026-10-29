<?php

namespace App\Support;

use App\Models\AccommodationRoom;
use Illuminate\Support\Collection;

/**
 * Finds rooms from what a manager types: names ("NB-204", "nb 204"), numbers ("204") and ranges
 * ("101-110", "NB-101 to NB-110"). Numbers match the last number in a room's name, so "204"
 * finds "NB-204"; a letter prefix on a range ("NB-") keeps it to rooms starting with those letters.
 */
class RoomRange
{
    /**
     * The rooms a comma-separated list picks out, and the parts that matched no room.
     *
     * @param  Collection<int, AccommodationRoom>  $rooms
     * @return array{ids: list<int>, unmatched: list<string>}
     */
    public static function parse(Collection $rooms, string $text): array
    {
        $ids = [];
        $unmatched = [];
        foreach (preg_split('/[,;\n]+/', $text) as $part) {
            $part = trim($part);
            if ($part === '') {
                continue;
            }
            $found = self::find($rooms, $part);
            $found->isEmpty() ? $unmatched[] = $part : array_push($ids, ...$found->modelKeys());
        }

        return ['ids' => array_values(array_unique($ids)), 'unmatched' => $unmatched];
    }

    /**
     * The rooms one name, number or range stands for.
     *
     * @param  Collection<int, AccommodationRoom>  $rooms
     * @return Collection<int, AccommodationRoom>
     */
    public static function find(Collection $rooms, string $text): Collection
    {
        $exact = $rooms->filter(fn (AccommodationRoom $room) => self::key($room->name) === self::key($text));
        if ($exact->isNotEmpty() || self::key($text) === '') {
            return $exact->values();
        }

        if (preg_match('/^(.*?)(\d+)\s*(?:-|–|to)\s*(.*?)(\d+)$/iu', trim($text), $range)) {
            [$low, $high] = [(int) $range[2], (int) $range[4]];
            [$low, $high] = [min($low, $high), max($low, $high)];
            $prefix = preg_replace('/[^a-z]/', '', strtolower($range[1]));

            return $rooms->filter(fn (AccommodationRoom $room) => ($number = self::number($room->name)) !== null
                && $number >= $low && $number <= $high
                && ($prefix === '' || str_starts_with(self::key($room->name), $prefix)))->values();
        }

        return ctype_digit(trim($text))
            ? $rooms->filter(fn (AccommodationRoom $room) => self::number($room->name) === (int) $text)->values()
            : collect();
    }

    /** The last number in a room's name ("NB-204" is 204, "OB-002" is 2), if it has one. */
    public static function number(string $name): ?int
    {
        return preg_match('/(\d+)\D*$/', $name, $match) ? (int) $match[1] : null;
    }

    /** A room name compared without case, spaces or punctuation. */
    private static function key(string $name): string
    {
        return preg_replace('/[^a-z0-9]/', '', strtolower($name));
    }
}
