<?php

namespace App\Exports\Concerns;

use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Cell\DefaultValueBinder;

/**
 * Writes text as text. Excel otherwise turns a phone like "+2348091000008" into the number
 * 2348091000008 (shown as 2.34809E+12), dropping the "+". Counts are numbers, not strings, so
 * they stay numbers. Use with WithCustomValueBinder.
 */
trait KeepsTextAsText
{
    public function bindValue(Cell $cell, mixed $value): bool
    {
        if (is_string($value)) {
            $cell->setValueExplicit($value, DataType::TYPE_STRING);

            return true;
        }

        return (new DefaultValueBinder)->bindValue($cell, $value);
    }
}
