<?php

namespace App\Services;

use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\Participant;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Spots attendees who are probably the same person under a different spelling or word order:
 * "Ebenezer Quao" / "Ebenzer Quao", "Sayeweh Charles" / "Charles Garvan Sayeweh". Every word of
 * the shorter name (at least two) must match a word of the longer one, exactly or one letter
 * off. Two different phone numbers, or two different genders, mean two people.
 */
class SimilarNames
{
    private const TITLES = ['mr', 'mrs', 'ms', 'miss', 'dr', 'prof', 'rev', 'revd', 'reverend', 'pastor', 'pst', 'apostle', 'aps', 'apst',
        'prophet', 'prophetess', 'bishop', 'evangelist', 'evang', 'elder', 'eld', 'deacon', 'deaconess', 'overseer', 'lady', 'sister', 'brother', 'jnr', 'snr'];

    /** @return array<int, string> */
    public function words(?string $name): array
    {
        $words = preg_split('/[^a-z]+/', strtolower(Str::ascii((string) $name)), -1, PREG_SPLIT_NO_EMPTY);

        return array_values(array_unique(array_filter($words, fn ($word) => strlen($word) > 2 && ! in_array($word, self::TITLES, true))));
    }

    public function sameName(?string $a, ?string $b): bool
    {
        [$short, $long] = collect([$this->words($a), $this->words($b)])->sortBy(fn ($words) => count($words))->values()->all();
        if (count($short) < 2) {
            return false;
        }

        foreach ($short as $word) {
            if (! collect($long)->contains(fn ($other) => $this->sameWord($word, $other))) {
                return false;
            }
        }

        return true;
    }

    public function samePerson(Participant $a, Participant $b): bool
    {
        if ($a->id === $b->id || Participant::isNumberedPlaceholderName($a->name) || Participant::isNumberedPlaceholderName($b->name)) {
            return false;
        }
        $phoneA = $this->phoneDigits($a->phone);
        $phoneB = $this->phoneDigits($b->phone);
        if ($phoneA && $phoneB && $phoneA !== $phoneB) {
            return false;
        }
        if (filled($a->gender) && filled($b->gender) && strtolower($a->gender) !== strtolower($b->gender)) {
            return false;
        }

        return $this->sameName($a->name, $b->name);
    }

    /**
     * Groups of registrations that look like one person, for the possible-duplicates panel.
     *
     * @param  Collection<int, EventRegistration>  $registrations  with participant loaded
     * @return Collection<int, Collection<int, EventRegistration>>
     */
    public function groups(Collection $registrations): Collection
    {
        $registrations = $registrations->values();
        $parent = range(0, max(0, $registrations->count() - 1));
        $find = function (int $i) use (&$parent, &$find): int {
            return $parent[$i] === $i ? $i : $parent[$i] = $find($parent[$i]);
        };

        $nameKey = fn (EventRegistration $registration) => app(ParticipantRegistrationService::class)->nameKey($registration->participant->name);
        foreach ($this->candidatePairs($registrations) as [$i, $j]) {
            $a = $registrations[$i];
            $b = $registrations[$j];
            // The exact same name is always worth a look, whatever the phone numbers say.
            $sameExactName = $a->participant_id !== $b->participant_id && $nameKey($a) !== '' && $nameKey($a) === $nameKey($b);
            if ($sameExactName || $this->samePerson($a->participant, $b->participant)) {
                $parent[$find($i)] = $find($j);
            }
        }

        return $registrations->keys()->groupBy(fn ($i) => $find($i))
            ->filter(fn ($indexes) => $indexes->count() > 1)
            ->map(fn ($indexes) => $indexes->map(fn ($i) => $registrations[$i])->values())
            ->values();
    }

    /**
     * Registrations on this event (not cancelled) that look like the person described.
     *
     * @return Collection<int, EventRegistration>
     */
    public function matchesOnEvent(Event $event, string $name, ?string $phone = null, ?string $gender = null): Collection
    {
        $words = $this->words($name);
        if (count($words) < 2) {
            return collect();
        }

        $candidate = new Participant(['name' => $name, 'phone' => $phone, 'gender' => $gender]);

        return $event->registrations()
            ->where('status', '!=', EventRegistration::STATUS_CANCELLED)
            ->whereHas('participant', function ($query) use ($words): void {
                $query->where('is_support_staff', false)->where(function ($query) use ($words): void {
                    foreach ($words as $word) {
                        $query->orWhereRaw('LOWER(name) LIKE ?', ['%'.substr($word, 0, 3).'%']);
                    }
                });
            })
            ->with('participant')
            ->get()
            ->filter(fn (EventRegistration $registration) => $this->samePerson($candidate, $registration->participant))
            ->values();
    }

    private function sameWord(string $a, string $b): bool
    {
        return $a === $b || (strlen($a) > 3 && strlen($b) > 3 && levenshtein($a, $b) <= 1);
    }

    private function phoneDigits(?string $phone): ?string
    {
        $digits = substr(preg_replace('/\D+/', '', (string) $phone), -9);

        return strlen($digits) === 9 ? $digits : null;
    }

    /** Index pairs sharing a word's first three letters: the only ones that can match. */
    private function candidatePairs(Collection $registrations): array
    {
        $buckets = [];
        foreach ($registrations as $i => $registration) {
            foreach ($this->words($registration->participant->name) as $word) {
                $buckets[substr($word, 0, 3)][$i] = true;
            }
        }

        $pairs = [];
        foreach ($buckets as $indexes) {
            $indexes = array_keys($indexes);
            foreach ($indexes as $x => $i) {
                foreach (array_slice($indexes, $x + 1) as $j) {
                    $pairs[$i < $j ? "$i-$j" : "$j-$i"] = [min($i, $j), max($i, $j)];
                }
            }
        }

        return array_values($pairs);
    }
}
