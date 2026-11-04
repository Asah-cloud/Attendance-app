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
        return $this->sameWords($this->words($a), $this->words($b));
    }

    public function samePerson(Participant $a, Participant $b): bool
    {
        return $a->id !== $b->id && $this->sameProfile($this->profile($a), $this->profile($b));
    }

    /** @return array{words: array<int, string>, phone: ?string, gender: string, placeholder: bool} */
    private function profile(Participant $participant): array
    {
        return [
            'words' => $this->words($participant->name),
            'phone' => $this->phoneDigits($participant->phone),
            'gender' => strtolower(trim((string) $participant->gender)),
            'placeholder' => Participant::isNumberedPlaceholderName($participant->name),
        ];
    }

    private function sameProfile(array $a, array $b): bool
    {
        if ($a['placeholder'] || $b['placeholder']) {
            return false;
        }
        if ($a['phone'] && $b['phone'] && $a['phone'] !== $b['phone']) {
            return false;
        }
        if ($a['gender'] !== '' && $b['gender'] !== '' && $a['gender'] !== $b['gender']) {
            return false;
        }

        return $this->sameWords($a['words'], $b['words']);
    }

    private function sameWords(array $a, array $b): bool
    {
        [$short, $long] = count($a) <= count($b) ? [$a, $b] : [$b, $a];
        if (count($short) < 2) {
            return false;
        }
        foreach ($short as $word) {
            $found = false;
            foreach ($long as $other) {
                if ($this->sameWord($word, $other)) {
                    $found = true;
                    break;
                }
            }
            if (! $found) {
                return false;
            }
        }

        return true;
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

        $people = app(ParticipantRegistrationService::class);
        $profiles = $registrations->map(fn (EventRegistration $registration) => $this->profile($registration->participant))->all();
        $keys = $registrations->map(fn (EventRegistration $registration) => $people->nameKey($registration->participant->name))->all();
        foreach ($this->candidatePairs($profiles) as [$i, $j]) {
            if ($registrations[$i]->participant_id === $registrations[$j]->participant_id) {
                continue;
            }
            // The exact same name is always worth a look, whatever the phone numbers say.
            $sameExactName = $keys[$i] !== '' && $keys[$i] === $keys[$j];
            if ($sameExactName || $this->sameProfile($profiles[$i], $profiles[$j])) {
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
    private function candidatePairs(array $profiles): array
    {
        $buckets = [];
        foreach ($profiles as $i => $profile) {
            foreach ($profile['words'] as $word) {
                $buckets[substr($word, 0, 3)][$i] = true;
            }
        }

        $pairs = [];
        foreach ($buckets as $indexes) {
            $indexes = array_keys($indexes);
            foreach ($indexes as $x => $i) {
                foreach (array_slice($indexes, $x + 1) as $j) {
                    $pairs[$i * 100000 + $j] = [$i, $j];
                }
            }
        }

        return array_values($pairs);
    }
}
