<?php

namespace App\Exports;

use App\Exports\Concerns\KeepsTextAsText;
use App\Exports\Concerns\StylesReportSheet;
use App\Models\BadgePrint;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Support\BadgeDesign;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithCustomStartCell;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;

/** Everyone whose badge has been printed, with the details on their latest badge. */
class BadgePrintListExport implements FromArray, WithCustomStartCell, WithCustomValueBinder, WithEvents, WithHeadings, WithTitle
{
    use KeepsTextAsText, StylesReportSheet;

    /** @var Collection<int, Collection<int, BadgePrint>> Prints per registration, latest first. */
    private Collection $prints;

    /** @var list<string> Badge field labels filled in on at least one printed badge. */
    private array $labels;

    /** @param  Event  $event  Carrying the design in use (staff settings applied for staff badges). */
    public function __construct(private readonly Event $event, private readonly bool $staffMode)
    {
        $this->prints = BadgePrint::where('event_id', $event->id)
            ->whereHas('registration.participant', fn ($query) => $query->where('is_support_staff', $staffMode))
            ->with(['user:id,name', 'registration.participant', 'registration.roomAssignment.room'])
            ->latest()->latest('id')->get()
            ->groupBy('event_registration_id')
            ->sortBy(fn ($prints) => mb_strtolower($prints->first()->registration->participant->name));
        // A field shown on badges but blank on all of them (no rooms yet, say) gets no column.
        $used = $this->prints->flatMap(fn ($prints) => array_keys(array_filter($prints->first()->details, fn ($value) => $value !== '')))->unique();
        $this->labels = collect([...BadgePrint::PERSONAL_FIELDS, 'custom'])->map(fn ($key) => BadgeDesign::LABELS[$key])->filter(fn ($label) => $used->contains($label))->values()->all();
    }

    public function title(): string
    {
        return 'Printed badges';
    }

    public function startCell(): string
    {
        return 'A'.$this->headerRow;
    }

    public function headings(): array
    {
        return ['#', 'Name', ...array_map(fn ($label) => 'On badge: '.$label, $this->labels), 'Last printed', 'Times printed', 'Printed by', 'Note'];
    }

    public function array(): array
    {
        return $this->prints->values()->map(function (Collection $prints, int $index) {
            $latest = $prints->first();
            $registration = $latest->registration;

            return [
                $index + 1, $registration->participant->name,
                ...array_map(fn ($label) => $latest->details[$label] ?? '', $this->labels),
                $latest->created_at->format('j M Y, g:i A'), $prints->count(),
                $latest->user?->name ?? '', $this->note($registration, $latest),
            ];
        })->all();
    }

    /** Why a badge may need reprinting, or how it came to be on the list. */
    private function note(EventRegistration $registration, BadgePrint $latest): string
    {
        if ($registration->status !== EventRegistration::STATUS_CONFIRMED) {
            return 'No longer confirmed';
        }
        $now = BadgeDesign::values($this->event, $registration);
        $changed = collect(BadgePrint::PERSONAL_FIELDS)->contains(fn ($key) => array_key_exists(BadgeDesign::LABELS[$key], $latest->details)
            && $latest->details[BadgeDesign::LABELS[$key]] !== (string) $now[$key]);

        return match (true) {
            $changed => 'Details changed since printing',
            $latest->marked => 'Marked as printed',
            default => '',
        };
    }

    public function registerEvents(): array
    {
        return [AfterSheet::class => function (AfterSheet $event): void {
            $sheet = $event->sheet->getDelegate();
            $this->writeTitleBlock($sheet, $this->event->title, ($this->staffMode ? 'Printed staff badges' : 'Printed badges').'  ·  '.$this->prints->count().' people  ·  Generated '.now()->format('j M Y, g:i A'));
            $this->styleTable($sheet, $this->headerRow, count($this->headings()), [1, count($this->headings()) - 2]);
        }];
    }
}
