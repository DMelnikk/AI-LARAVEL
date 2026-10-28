<?php

namespace App\Ai\Tools;

use App\Models\Ticket;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class TicketsFactsTool implements Tool
{
    public function __construct(
        public int $ticketId,
        public ?User $user = null
    ) {
    }

    /**
     * Get the description of the tool's purpose.
     */
    public function description(): Stringable|string
    {
        return 'Fetch key facts about the current ticket (status, priority, department, sentiment, tags)';
    }

    /**
     * Execute the tool.
     */
    public function handle(Request $request): Stringable|string
    {
        $user = $this->user ?? auth()->user();

        if(!$user) {
            return 'Unauthorized';
        }

        $ticket = Ticket::with('tags')->find($this->ticketId);

        // TODO: check if user can view this ticket
        if(!$ticket) {
            return 'Unauthorized';
        }

        $payload = [
          'id' => $ticket->id,
          'subject' => $ticket->subject,
          'status' => $ticket->status,
          'priority' => $ticket->priority,
          'department' => $ticket->department,
          'sentiment' => $ticket->sentiment,
          'tags' => $ticket->tags->pluck('name')->values(),
        ];

        return json_encode($payload, JSON_PRETTY_PRINT);
    }

    /**
     * Get the tool's schema definition.
     */
    public function schema(JsonSchema $schema): array
    {
        return [];
    }
}
