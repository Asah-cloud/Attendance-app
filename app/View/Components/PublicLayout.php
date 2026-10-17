<?php

namespace App\View\Components;

use App\Models\Event;
use Illuminate\View\Component;
use Illuminate\View\View;

class PublicLayout extends Component
{
    public function __construct(
        public ?string $title = null,
        public bool $noindex = false,
        public ?string $description = null,
        public ?string $image = null,
        // A page attendees open for one event (such as a form): it carries the organiser's
        // branding instead of the Asah Apex site menu and footer, with a "Powered by" line.
        public ?Event $brandedFor = null,
    ) {}

    public function render(): View
    {
        return view('layouts.public');
    }
}
