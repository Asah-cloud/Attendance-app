<?php

namespace App\Services;

use RuntimeException;
use Smalot\PdfParser\Page;
use Smalot\PdfParser\Parser;

class PdfParticipantListParser
{
    /**
     * Extract participant rows from a text-based PDF table.
     *
     * @return array<int, array<int, string|null>> Rows in the standard import order:
     *                                             ID, name, gender, room group, category, phone, email.
     */
    public function parse(string $path): array
    {
        $pages = (new Parser)->parseFile($path)->getPages();
        $tablePages = [];
        $continuationPages = [];
        $columns = null;
        $contactColumns = null;

        foreach ($pages as $page) {
            $lines = $this->positionedLines($page);
            $header = $this->findHeader($lines);

            if ($header && isset($header['name'])) {
                $columns = $header + ($columns ?? []);
            }
            if ($header && (isset($header['phone']) || isset($header['email']))) {
                $contactColumns = $header;
            }

            if ($this->containsParticipantRows($lines)) {
                $tablePages[] = ['lines' => $lines, 'columns' => $columns];
            } elseif ($this->containsContactRows($lines)) {
                $continuationPages[] = ['lines' => $lines, 'columns' => $contactColumns ?? $header ?? []];
            }
        }

        if ($tablePages === [] || ! isset($columns['name'])) {
            throw new RuntimeException('No participant table was found. The PDF must contain selectable text with at least ID and Name columns. Scanned image PDFs are not supported.');
        }

        $rows = [];
        foreach ($tablePages as $pageIndex => $tablePage) {
            $pageRows = $this->extractRows($tablePage['lines'], $tablePage['columns']);
            $contactLines = $continuationPages[$pageIndex]['lines'] ?? [];
            $contactColumns = $continuationPages[$pageIndex]['columns'] ?? [];

            foreach ($pageRows as $row) {
                if ($contactLines !== []) {
                    $matchingLine = $this->nearestLine($contactLines, $row['y']);
                    if ($matchingLine && abs($matchingLine['y'] - $row['y']) < 3) {
                        $row['phone'] ??= $this->valueForColumn($matchingLine, 'phone', $contactColumns);
                        $row['email'] ??= $this->valueForColumn($matchingLine, 'email', $contactColumns);
                    }
                }

                $rows[] = [
                    $row['id'] ?? null,
                    $row['name'],
                    $row['gender'] ?? null,
                    $row['room_group'] ?? null,
                    $row['category'] ?? 'Member',
                    $row['phone'] ?? null,
                    $row['email'] ?? null,
                ];
            }
        }

        if ($rows === []) {
            throw new RuntimeException('The PDF table was found, but no participant rows could be read.');
        }

        return $rows;
    }

    /** @return array<int, array{y: float, cells: array<int, array{x: float, text: string}>}> */
    private function positionedLines(Page $page): array
    {
        $lines = [];

        foreach ($page->getDataTm() as $item) {
            $matrix = $item[0] ?? null;
            $text = trim((string) ($item[1] ?? ''));
            if (! is_array($matrix) || $text === '') {
                continue;
            }

            $x = (float) ($matrix[4] ?? 0);
            $y = (float) ($matrix[5] ?? 0);
            $key = (string) round($y, 1);
            $lines[$key] ??= ['y' => $y, 'cells' => []];
            $lines[$key]['cells'][] = ['x' => $x, 'text' => preg_replace('/\s+/', ' ', $text) ?? $text];
        }

        foreach ($lines as &$line) {
            usort($line['cells'], fn ($a, $b) => $a['x'] <=> $b['x']);
        }

        return array_values($lines);
    }

    /** @return array<string, float>|null */
    private function findHeader(array $lines): ?array
    {
        $aliases = [
            'id' => ['id', 'member id', 'no', 'number'],
            'name' => ['name', 'full name', 'participant', 'participant name'],
            'gender' => ['gender', 'sex'],
            'room_group' => ['area', 'room group', 'group', 'department'],
            'category' => ['category', 'type'],
            'phone' => ['phone', 'phone number', 'telephone', 'mobile'],
            'email' => ['email', 'email address'],
        ];

        foreach ($lines as $line) {
            $found = [];
            foreach ($line['cells'] as $cell) {
                $label = strtolower(trim($cell['text']));
                foreach ($aliases as $key => $labels) {
                    if (in_array($label, $labels, true)) {
                        $found[$key] = $cell['x'];
                    }
                }
            }
            if (isset($found['name']) || isset($found['phone']) || isset($found['email'])) {
                return $found;
            }
        }

        return null;
    }

    private function containsParticipantRows(array $lines): bool
    {
        return collect($lines)->contains(fn ($line) => preg_match('/^\d+\s*[A-Za-z]/', $this->lineText($line)) === 1)
            && collect($lines)->contains(fn ($line) => preg_match('/\b(?:male|female)\b/i', $this->lineText($line)) === 1);
    }

    private function containsContactRows(array $lines): bool
    {
        $contactValues = collect($lines)->filter(function ($line) {
            $text = $this->lineText($line);

            return preg_match('/^\+?[0-9][0-9\s()\-]{4,}$/', $text) === 1
                || filter_var($text, FILTER_VALIDATE_EMAIL);
        });

        return collect($lines)->contains(fn ($line) => preg_match('/\b(?:phone|email)\b/i', $this->lineText($line)) === 1)
            || $contactValues->count() >= 3;
    }

    /** @return array<int, array<string, mixed>> */
    private function extractRows(array $lines, array $columns): array
    {
        $rows = [];
        foreach ($lines as $line) {
            $text = $this->lineText($line);
            if (! preg_match('/^(\d+)\s*(.+)$/', $text, $match)) {
                continue;
            }

            $row = ['y' => $line['y'], 'id' => $match[1]];
            foreach (['name', 'gender', 'room_group', 'category', 'phone', 'email'] as $column) {
                $row[$column] = $this->valueForColumn($line, $column, $columns);
            }

            if ($row['name']) {
                $row['name'] = preg_replace('/^'.preg_quote($row['id'], '/').'\s+/', '', $row['name']) ?: $row['name'];
            }

            // Some PDF producers merge an entire row into one text object. Recover the
            // common ID / Name / Gender / Area / Category layout in that case.
            if ((! $row['name'] || preg_match('/\b(?:male|female)\b/i', $row['name']))
                && preg_match('/^(\d+)\s+(.+?)\s+(Male|Female)\s+(.+?)\s+([^\s]+)$/i', $text, $parts)) {
                $row['id'] = $parts[1];
                $row['name'] = $parts[2];
                $row['gender'] = ucfirst(strtolower($parts[3]));
                $row['room_group'] = $parts[4];
                $row['category'] = $parts[5];
            }

            if ($row['name']) {
                $rows[] = $row;
            }
        }

        return $rows;
    }

    private function valueForColumn(array $line, string $column, array $columns): ?string
    {
        if (! isset($columns[$column])) {
            return null;
        }

        $positions = $columns;
        asort($positions);
        $keys = array_keys($positions);
        $index = array_search($column, $keys, true);
        $left = $index === 0 ? -INF : ($positions[$keys[$index - 1]] + $positions[$column]) / 2;
        $right = $index === count($keys) - 1 ? INF : ($positions[$column] + $positions[$keys[$index + 1]]) / 2;

        $values = collect($line['cells'])
            ->filter(fn ($cell) => $cell['x'] >= $left && $cell['x'] < $right)
            ->pluck('text')
            ->filter()
            ->values();

        return $values->isEmpty() ? null : trim($values->implode(' '));
    }

    private function nearestLine(array $lines, float $y): ?array
    {
        return collect($lines)
            ->reject(fn ($line) => preg_match('/\b(?:phone|email)\b/i', $this->lineText($line)))
            ->sortBy(fn ($line) => abs($line['y'] - $y))
            ->first();
    }

    private function lineText(array $line): string
    {
        return trim(collect($line['cells'])->pluck('text')->implode(' '));
    }
}
