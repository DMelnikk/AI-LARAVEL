<?php

namespace App\Ai\Tools;

use App\Models\Ticket;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class TicketMessagesTool implements Tool
{
    public function __construct(
        public int $ticketId,
        public ?User $user = null,
    )
    {}

    /**
     * Get the description of the tool's purpose.
     */
    public function description(): Stringable|string
    {
        return 'Return the most recent ticket messages';
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

        $ticket = Ticket::find($this->ticketId);

        // TODO user can view ticket
        if(!$ticket) {
            return 'Unauthorized';
        }

        $count = $request->integer('count',3);
        $count = max(1, min(5, $count));

        $messages = $ticket->messages()
            ->latest()
            ->limit($count)
            ->get()
            ->reverse()
            ->map(fn($message) => [
                'role' => $message->role,
                'body' => $message->body,
            ])
            ->values();

            return $messages->toJson(JSON_PRETTY_PRINT);
    }

    /**
     * Get the tool's schema definition.
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'count' => $schema
                ->integer()
                ->min(1)
                ->max(5)
                ->description('How may recent messages to retrieve')
                ->required(),
        ];
    }
}
