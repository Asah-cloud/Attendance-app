<?php

namespace App\Exports;

use App\Exports\Concerns\WritesSummarySections;
use App\Models\Event;
use App\Services\RegistrationReport;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithStrictNullComparison;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;

/** First sheet of the registration download: people by status, then by category, gender, country and area. */
class RegistrationSummarySheet implements FromArray, WithEvents, WithStrictNullComparison, WithTitle
{
    use WritesSummarySections;

    public function __construct(private readonly Event $event, private readonly array $report) {}

    public function title(): string
    {
        return 'Summary';
    }

    public function array(): array
    {
        $report = $this->report;
        $total = $report['rows']->count();
        $share = fn (int $count) => $total ? round($count / $total * 100).'%' : '0%';

        $this->summaryTitle($this->event->title, 'Registrations: '.RegistrationReport::filterLabel($report['filters']).'  ·  Generated '.now()->format('j M Y, g:i A'));

        $this->section('By status', ['Status', 'People'], collect(RegistrationReport::STATUSES)
            ->filter(fn ($label, $status) => $report['statusCounts']->has($status))
            ->map(fn ($label, $status) => [$label, $report['statusCounts'][$status]])->values()->all());
        $this->section('In this report', ['', 'People'], [
            ['Registered', $total],
            ['Checked in at least once', $report['checkedIn']],
            ['Not checked in yet', $total - $report['checkedIn']],
        ]);
        foreach ($report['breakdowns'] as $heading => $counts) {
            $this->section('By '.strtolower($heading), [$heading === 'Country' ? 'Country (from phone number)' : $heading, 'People', 'Share'],
                $counts->map(fn ($count, $label) => [$label, $count, $share($count)])->values()->all());
        }

        return $this->lines;
    }

    public function registerEvents(): array
    {
        return [AfterSheet::class => fn (AfterSheet $event) => $this->styleSummary($event->sheet->getDelegate())];
    }
}
