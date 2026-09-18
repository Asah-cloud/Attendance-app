<?php

namespace App\Jobs;

use App\Models\BadgeExport;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;
use Throwable;
use ZipArchive;

class FinalizeBadgeExport implements ShouldQueue
{
    use Queueable;

    public int $timeout = 90;

    public int $tries = 1;

    public function __construct(public string $exportId)
    {
        $this->onQueue('badges');
    }

    public function handle(): void
    {
        $export = BadgeExport::findOrFail($this->exportId);
        $relative = "badge-exports/{$export->id}/badges.zip";
        $absolute = Storage::disk('local')->path($relative);
        $zip = new ZipArchive;
        if ($zip->open($absolute, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException('Could not create badge ZIP file.');
        }
        for ($batch = 1; $batch <= $export->total_batches; $batch++) {
            $pdf = Storage::disk('local')->path("badge-exports/{$export->id}/batch-{$batch}.pdf");
            if (! is_file($pdf)) {
                $zip->close();
                throw new \RuntimeException("Badge batch {$batch} is missing.");
            }
            $zip->addFile($pdf, sprintf('badges-%02d.pdf', $batch));
        }
        $zip->close();
        $export->update(['status' => 'ready', 'file_path' => $relative, 'error' => null]);
    }

    public function failed(?Throwable $exception): void
    {
        BadgeExport::find($this->exportId)?->update(['status' => 'failed', 'error' => 'The badge ZIP could not be completed. Please try again.']);
    }
}
