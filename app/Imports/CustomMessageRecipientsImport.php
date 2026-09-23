<?php

namespace App\Imports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;

/** Expects columns in order: Name, Email, Phone. The header row (if any) is skipped. */
class CustomMessageRecipientsImport implements ToCollection
{
    private const HEADER_LABELS = ['name', 'full name', 'email', 'phone', 'phone number'];

    /** @var Collection<int, array{name: string, email: ?string, phone: ?string}> */
    public Collection $rows;

    public function collection(Collection $rows): void
    {
        $this->rows = $rows
            ->filter(function ($row) {
                $first = strtolower(trim((string) ($row[0] ?? '')));

                return $first !== '' && ! in_array($first, self::HEADER_LABELS, true);
            })
            ->map(fn ($row) => [
                'name' => trim((string) ($row[0] ?? '')),
                'email' => trim((string) ($row[1] ?? '')) ?: null,
                'phone' => trim((string) ($row[2] ?? '')) ?: null,
            ])
            ->values();
    }
}
