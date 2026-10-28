<?php

namespace App\Jobs;

use App\Models\BadgeExport;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;
use setasign\Fpdi\Fpdi;
use Throwable;

class FinalizeBadgeExport implements ShouldQueue
{
    use Queueable;

    public int $timeout = 300;

    public int $tries = 1;

    public function __construct(public string $exportId)
    {
        $this->onQueue('badges');
    }

    /** Join the batch PDFs into one file, so a large print is printed in one go. */
    public function handle(): void
    {
        $export = BadgeExport::findOrFail($this->exportId);
        $directory = "badge-exports/{$export->id}";
        $pdf = new Fpdi;
        $pdf->SetAutoPageBreak(false);
        for ($batch = 1; $batch <= $export->total_batches; $batch++) {
            $path = Storage::disk('local')->path("{$directory}/batch-{$batch}.pdf");
            if (! is_file($path)) {
                throw new \RuntimeException("Badge batch {$batch} is missing.");
            }
            $pages = $pdf->setSourceFile($path);
            for ($page = 1; $page <= $pages; $page++) {
                $template = $pdf->importPage($page);
                $size = $pdf->getTemplateSize($template);
                $pdf->AddPage($size['orientation'], [$size['width'], $size['height']]);
                $pdf->useTemplate($template);
            }
        }
        $pdf->Output('F', Storage::disk('local')->path("{$directory}/badges.pdf"));
        for ($batch = 1; $batch <= $export->total_batches; $batch++) {
            Storage::disk('local')->delete("{$directory}/batch-{$batch}.pdf");
        }
        $export->update(['status' => 'ready', 'file_path' => "{$directory}/badges.pdf", 'error' => null]);
    }

    public function failed(?Throwable $exception): void
    {
        BadgeExport::find($this->exportId)?->update(['status' => 'failed', 'error' => 'The badge PDF could not be completed. Please try again.']);
    }
}
