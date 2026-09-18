<?php

namespace App\Services;

use App\Models\Event;
use App\Models\EventRegistration;
use App\Support\BadgeDesign;
use Barryvdh\DomPDF\Facade\Pdf;

class BadgePdfService
{
    public function registrations(Event $event, array $options, bool $staffMode)
    {
        return $event->registrations()
            ->where('status', EventRegistration::STATUS_CONFIRMED)
            ->whereHas('participant', fn ($query) => $query->where('is_support_staff', $staffMode))
            ->when(isset($options['attendees']), fn ($query) => $query->whereIn('id', $options['attendees']))
            ->when(filled($options['category'] ?? null), fn ($query) => $query->whereHas('participant', fn ($participant) => $participant->where('category', $options['category'])))
            ->with(['participant', 'roomAssignment.room.floor.block'])
            ->orderBy('id')
            ->when($options['sample'] ?? false, fn ($query) => $query->limit(1))
            ->get();
    }

    public function render(Event $event, $registrations, array $options, bool $staffMode): string
    {
        $event->loadMissing('company');
        $categories = $event->registrations()->where('status', EventRegistration::STATUS_CONFIRMED)
            ->whereHas('participant', fn ($query) => $query->where('is_support_staff', $staffMode))
            ->with('participant:id,category')->get()->pluck('participant.category')->filter()->unique()->sort()->values();
        $palette = ['#7C3AED', '#0F766E', '#B45309', '#BE123C', '#1D4ED8', '#4338CA'];
        $categoryColors = $categories->mapWithKeys(fn ($category, $index) => [
            $category => $event->badge_category_colors[$category] ?? $palette[$index % count($palette)],
        ]);
        $fields = BadgeDesign::fields($event);
        $paper = $options['paper'] ?? 'badge';
        $cutGuides = (bool) ($options['cut_guides'] ?? false);
        $pdf = Pdf::loadView('events.badges-pdf', compact('event', 'registrations', 'categoryColors', 'fields', 'paper', 'cutGuides'))
            ->setOption('fontHeightRatio', 1000 / 1164);
        if (! is_dir(storage_path('fonts'))) {
            mkdir(storage_path('fonts'), 0775, true);
        }
        $metrics = $pdf->getDomPDF()->getFontMetrics();
        $metrics->registerFont(['family' => 'Poppins', 'style' => 'normal', 'weight' => 'normal'], base_path('resources/fonts/Poppins.ttf'));
        $metrics->registerFont(['family' => 'Poppins', 'style' => 'normal', 'weight' => 'bold'], base_path('resources/fonts/Poppins-Bold.ttf'));
        $pdf->setPaper($paper === 'a4' ? 'a4' : ($event->badge_size === 'A5' ? 'a5' : 'a6'), $paper === 'a4' && $event->badge_size !== 'A5' ? 'landscape' : 'portrait');

        return $pdf->output();
    }
}
