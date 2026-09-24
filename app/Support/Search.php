<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Query\Builder as QueryBuilder;

/**
 * One consistent way to search text columns, so every search box in the app behaves the same:
 *
 *  - case-insensitive on every database (a plain LIKE is case-sensitive on PostgreSQL),
 *  - every word typed must match somewhere, in any order ("kofi mensah" finds "Mensah, Kofi"),
 *  - %, _ and \ typed by the user are matched literally instead of acting as wildcards,
 *  - phone columns also match a typed number regardless of spaces, dashes or a leading zero.
 */
class Search
{
    private const MAX_WORDS = 6;

    private const MAX_LENGTH = 100;

    /** @return list<string> */
    public static function words(?string $term): array
    {
        $term = mb_substr(trim((string) $term), 0, self::MAX_LENGTH);
        $words = preg_split('/\s+/u', mb_strtolower($term), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return array_slice($words, 0, self::MAX_WORDS);
    }

    /** Escape a value for use inside a LIKE pattern (paired with ESCAPE '\'). */
    public static function escape(string $value): string
    {
        return addcslashes($value, '%_\\');
    }

    /**
     * Keep only rows where every typed word matches at least one of the columns.
     *
     * @param  list<string>  $columns  text columns to search
     * @param  list<string>  $phoneColumns  phone columns, matched ignoring spaces/dashes/leading zero
     */
    public static function apply(EloquentBuilder|QueryBuilder|Relation $query, ?string $term, array $columns, array $phoneColumns = []): EloquentBuilder|QueryBuilder|Relation
    {
        foreach (self::words($term) as $word) {
            $query->where(fn ($group) => self::matchWord($group, $word, $columns, $phoneColumns));
        }

        return $query;
    }

    /**
     * Add an "any column matches this one word" group of OR conditions. Also usable on its own
     * for searches that span several tables (call it inside each relation's closure).
     *
     * @param  list<string>  $columns
     * @param  list<string>  $phoneColumns
     */
    public static function matchWord(EloquentBuilder|QueryBuilder|Relation $query, string $word, array $columns, array $phoneColumns = []): void
    {
        $base = $query instanceof Relation ? $query->getQuery() : $query;
        $grammar = ($base instanceof EloquentBuilder ? $base->getQuery() : $base)->getGrammar();
        $like = '%'.self::escape($word).'%';
        $digits = preg_replace('/\D+/', '', $word) ?? '';
        $phoneLike = strlen($digits) >= 3 ? '%'.self::escape(ltrim($digits, '0')).'%' : null;

        foreach ($columns as $column) {
            $query->orWhereRaw('LOWER('.$grammar->wrap($column).") LIKE ? ESCAPE '\\'", [$like]);
        }

        foreach ($phoneColumns as $column) {
            $wrapped = $grammar->wrap($column);
            $query->orWhereRaw('LOWER('.$wrapped.") LIKE ? ESCAPE '\\'", [$like]);

            if ($phoneLike !== null) {
                $bare = "REPLACE(REPLACE(REPLACE(REPLACE(REPLACE({$wrapped}, ' ', ''), '-', ''), '+', ''), '(', ''), ')', '')";
                $query->orWhereRaw($bare." LIKE ? ESCAPE '\\'", [$phoneLike]);
            }
        }
    }
}
