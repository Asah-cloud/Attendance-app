<?php

namespace App\Exports\Concerns;

use App\Services\AttendanceGrid;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * The look every attendance download shares: a title block, a dark header row that stays
 * on screen, filters, light row stripes, sensible column widths and a printable page.
 */
trait StylesReportSheet
{
    /** Header row of the table; rows above it hold the title block. */
    protected int $headerRow = 4;

    protected function writeTitleBlock(Worksheet $sheet, string $title, string $subtitle): void
    {
        $sheet->setCellValue('A1', $title);
        $sheet->setCellValue('A2', $subtitle);
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16)->getColor()->setRGB('0F172A');
        $sheet->getStyle('A2')->getFont()->setSize(10)->getColor()->setRGB('64748B');
        $sheet->getRowDimension(1)->setRowHeight(24);
    }

    /**
     * @param  array<int, int>  $centred  1-based column numbers to centre.
     * @param  array<int, int>  $dayColumns  1-based column numbers holding check-in times.
     */
    protected function styleTable(Worksheet $sheet, int $headerRow, int $columns, array $centred = [], array $dayColumns = []): void
    {
        $lastColumn = Coordinate::stringFromColumnIndex($columns);
        $lastRow = max($headerRow, $sheet->getHighestDataRow());

        $header = $sheet->getStyle("A{$headerRow}:{$lastColumn}{$headerRow}");
        $header->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
        $header->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('1E3A8A');
        $header->getAlignment()->setVertical(Alignment::VERTICAL_CENTER)->setWrapText(true);
        $sheet->getRowDimension($headerRow)->setRowHeight(22);

        if ($lastRow > $headerRow) {
            $body = $sheet->getStyle('A'.($headerRow + 1).":{$lastColumn}{$lastRow}");
            $body->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB('E2E8F0');
            $body->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
            for ($row = $headerRow + 2; $row <= $lastRow; $row += 2) {
                $sheet->getStyle("A{$row}:{$lastColumn}{$row}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('F8FAFC');
            }
            foreach ($dayColumns as $column) {
                $letter = Coordinate::stringFromColumnIndex($column);
                for ($row = $headerRow + 1; $row <= $lastRow; $row++) {
                    $value = (string) $sheet->getCell("{$letter}{$row}")->getValue();
                    $style = $sheet->getStyle("{$letter}{$row}");
                    if ($value === AttendanceGrid::NOT_PRESENT) {
                        $style->getFont()->getColor()->setRGB('CBD5E1');
                    } elseif ($value !== '') {
                        $style->getFont()->setBold(true)->getColor()->setRGB('166534');
                        $style->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('DCFCE7');
                    }
                }
            }
            $sheet->setAutoFilter("A{$headerRow}:{$lastColumn}{$lastRow}");
        }

        foreach (array_unique([...$centred, ...$dayColumns]) as $column) {
            $letter = Coordinate::stringFromColumnIndex($column);
            $sheet->getStyle("{$letter}{$headerRow}:{$letter}{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        }

        $this->fitColumns($sheet, $headerRow, $columns, $lastRow);
        $sheet->freezePane('A'.($headerRow + 1));
        $sheet->getPageSetup()->setOrientation(PageSetup::ORIENTATION_LANDSCAPE)->setPaperSize(PageSetup::PAPERSIZE_A4)
            ->setFitToWidth(1)->setFitToHeight(0)->setRowsToRepeatAtTopByStartAndEnd($headerRow, $headerRow);
    }

    /** Width from the longest value in each column; the title block is ignored so it never stretches column A. */
    private function fitColumns(Worksheet $sheet, int $headerRow, int $columns, int $lastRow): void
    {
        for ($column = 1; $column <= $columns; $column++) {
            $letter = Coordinate::stringFromColumnIndex($column);
            $longest = 4;
            for ($row = $headerRow; $row <= $lastRow; $row++) {
                $longest = max($longest, mb_strlen((string) $sheet->getCell("{$letter}{$row}")->getValue()));
            }
            $sheet->getColumnDimension($letter)->setWidth(min(45, $longest + 3));
        }
    }
}
