<?php

namespace App\Exports\Concerns;

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * A summary sheet made of titled tables stacked down the page (Overview, By category, ...):
 * build the rows with summaryTitle() and section(), then call styleSummary() after the sheet is written.
 */
trait WritesSummarySections
{
    /** @var array<int, array<int, mixed>> */
    private array $lines = [];

    /** @var array<int, array{row: int, width: int, rows: int}> Table header rows, for styling. */
    private array $tables = [];

    /** @var array<int, int> Section heading rows. */
    private array $headings = [];

    private int $widest = 2;

    private function summaryTitle(string $title, string $subtitle): void
    {
        // Blank rows must hold a value: an empty array is read as "no rows" and dropped.
        $this->lines = [[$title], [$subtitle], ['']];
        $this->tables = [];
        $this->headings = [];
    }

    private function section(string $heading, array $header, array $body): void
    {
        $this->headings[] = count($this->lines) + 1;
        $this->lines[] = [$heading];
        $this->tables[] = ['row' => count($this->lines) + 1, 'width' => count($header), 'rows' => count($body)];
        $this->widest = max($this->widest, count($header));
        $this->lines[] = $header;
        array_push($this->lines, ...$body);
        $this->lines[] = [''];
    }

    private function styleSummary(Worksheet $sheet): void
    {
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16)->getColor()->setRGB('0F172A');
        $sheet->getStyle('A2')->getFont()->setSize(10)->getColor()->setRGB('64748B');
        $sheet->getRowDimension(1)->setRowHeight(24);
        foreach ($this->headings as $row) {
            $sheet->getStyle("A{$row}")->getFont()->setBold(true)->setSize(12)->getColor()->setRGB('1E3A8A');
        }
        foreach ($this->tables as $table) {
            $last = Coordinate::stringFromColumnIndex($table['width']);
            $header = $sheet->getStyle("A{$table['row']}:{$last}{$table['row']}");
            $header->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
            $header->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('1E3A8A');
            $end = $table['row'] + max(1, $table['rows']);
            $sheet->getStyle("A{$table['row']}:{$last}{$end}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB('E2E8F0');
            if ($table['width'] > 1) {
                $sheet->getStyle("B{$table['row']}:{$last}{$end}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            }
        }
        $sheet->getColumnDimension('A')->setWidth(34);
        for ($column = 2; $column <= $this->widest; $column++) {
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($column))->setWidth(16);
        }
    }
}
