<?php

namespace App\Services;

use App\Imports\UsersImport;
use App\Models\Event;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;

/** Runs a stored participant list (Excel, CSV or text-based PDF) through UsersImport. */
class ParticipantListImporter
{
    public function __construct(private readonly PdfParticipantListParser $pdfParser) {}

    /**
     * A trial runs inside a transaction that is always rolled back, so it reports what would
     * happen - including rows that match people added by earlier rows of the same file -
     * without saving anything. $afterImport runs inside the same transaction, so a trial can
     * also preview what follows from the import (such as a room plan).
     */
    public function run(Event $event, string $path, bool $needsRoom, ?array $decisions, bool $trial, ?callable $afterImport = null): UsersImport
    {
        $import = new UsersImport($event, $needsRoom, $decisions ?? [], $trial);
        $run = function () use ($import, $path, $afterImport): void {
            if (strtolower(pathinfo($path, PATHINFO_EXTENSION)) === 'pdf') {
                foreach ($this->pdfParser->parse(Storage::disk('local')->path($path)) as $index => $row) {
                    $import->importRow($row, $index + 2);
                }
            } else {
                Excel::import($import, $path, 'local');
            }
            if ($afterImport) {
                $afterImport($import);
            }
        };

        if (! $trial) {
            DB::transaction($run);

            return $import;
        }

        DB::beginTransaction();
        try {
            $run();
        } finally {
            DB::rollBack();
        }

        return $import;
    }
}
