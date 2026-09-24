<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\MessageTemplate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/** Reusable messages a company can start from. Shared by every event of the company. */
class MessageTemplateController extends Controller
{
    public function store(Request $request, Event $event): JsonResponse
    {
        $this->authorize('manageMessages', $event);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'subject' => ['nullable', 'string', 'max:255'],
            'email_body' => ['nullable', 'string', 'max:5000'],
            'sms_body' => ['nullable', 'string', 'max:1000'],
        ]);

        if (! filled($validated['email_body'] ?? null) && ! filled($validated['sms_body'] ?? null)) {
            throw ValidationException::withMessages(['name' => 'Write an email or a text message before saving it as a template.']);
        }

        // Saving under an existing name updates that template instead of failing.
        $template = MessageTemplate::updateOrCreate(
            ['company_id' => $event->company_id, 'name' => trim($validated['name'])],
            [
                'subject' => $validated['subject'] ?? null,
                'email_body' => $validated['email_body'] ?? null,
                'sms_body' => $validated['sms_body'] ?? null,
                'created_by' => $request->user()->id,
            ],
        );

        return response()->json($template->only(['id', 'name', 'subject', 'email_body', 'sms_body']) + [
            'url' => route('events.message-templates.destroy', [$event, $template->id]),
            'updated' => ! $template->wasRecentlyCreated,
        ]);
    }

    public function destroy(Event $event, MessageTemplate $template): JsonResponse
    {
        $this->authorize('manageMessages', $event);
        abort_unless($template->company_id === $event->company_id, 404);

        $template->delete();

        return response()->json(['deleted' => true]);
    }
}
