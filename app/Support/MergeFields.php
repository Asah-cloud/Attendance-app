<?php

namespace App\Support;

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
            fn (string $token) => ! array_key_exists(strtolower(trim($token, '{} ')), self::TOKENS),
        )));
    }
}
