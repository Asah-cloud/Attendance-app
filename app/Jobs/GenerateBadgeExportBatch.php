<?php

namespace App\Jobs;

use App\Models\BadgeExport;
use App\Services\BadgePdfService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;
use Throwable;

class GenerateBadgeExportBatch implements ShouldQueue
{
    use Queueable;

    public int $timeout = 90;

    public int $tries = 1;

    public function __construct(public string $exportId, public array $registrationIds, public int $batchNumber)
    {
        $this->onQueue('badges');
    }

    public function handle(BadgePdfService $badges): void
    {
        $export = BadgeExport::with('event')->findOrFail($this->exportId);
        $export->update(['status' => 'processing']);
        $options = $export->options;
        $options['attendees'] = $this->registrationIds;
        $registrations = $badges->registrations($export->event, $options, (bool) ($options['staff'] ?? false));
        Storage::disk('local')->put("badge-exports/{$export->id}/batch-{$this->batchNumber}.pdf", $badges->render($export->event, $registrations, $options, (bool) ($options['staff'] ?? false)));
        $export->increment('completed_batches');
    }

    public function failed(?Throwable $exception): void
    {
        BadgeExport::find($this->exportId)?->update(['status' => 'failed', 'error' => 'A badge batch could not be generated. Please try again.']);
    }
}
