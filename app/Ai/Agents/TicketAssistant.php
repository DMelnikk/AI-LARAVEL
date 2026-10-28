<?php

namespace App\Ai\Agents;

use App\Ai\Tools\TicketMessagesTool;
use App\Ai\Tools\TicketsFactsTool;
use App\Models\Ticket;
use App\Models\User;
use Laravel\Ai\Attributes\MaxSteps;
use Laravel\Ai\Attributes\MaxTokens;
use Laravel\Ai\Attributes\Provider;
use Laravel\Ai\Concerns\RemembersConversations;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\Conversational;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Promptable;
use Stringable;

#[Provider(Lab::Gemini)]
#[MaxTokens(2500)]
#[MaxSteps(3)]
class TicketAssistant implements Agent, Conversational, HasTools
{
    use Promptable, RemembersConversations;

    public function __construct(public readonly int $ticketId, public readonly ?int $userId = null)
    {}

    public function instructions(): Stringable|string
    {
        $context = $this->ticketContext();
        return <<<PROMPT
        You are a support assistant. Stay strickly within the current ticket.
        If you are unsure, ask a clarifying question.
        $context
        PROMPT;
    }

    public function ticketContext(): string
    {
        $ticket = Ticket::with([
            'messages' => fn($query) => $query->latest()->limit(5),
            'tags',
        ])->find($this->ticketId);

        if (!$ticket) {
            return 'Ticket context : unavailable';
        }

        $tags = $ticket->tags->pluck('name')->implode(', ');
        $department = $ticket->department ?? 'n/a';
        $sentiment = $ticket->sentiment ?? 'n/a';
        $tagsText = $tags ?: 'none';
        $messages = $ticket->messages
            ->reverse()
            ->map(fn($message) => sprintf('%s: %s', $message->role, $message->body))
            ->implode("\n");

        return <<<CONTEXT
        Ticket Context:
            - Subject: {$ticket->subject}
            - Status: {$ticket->status}
            - Priority: {$ticket->priority}
            - Department: {$department}
            - Sentiment: {$sentiment}
            - TagsText: {$tagsText}

            Recent Messages:
            $messages
        CONTEXT;
    }


    public function tools(): iterable
    {
        $user = $this->userId ? User::find($this->userId): null;

        return [
            new TicketsFactsTool($this->ticketId, $user),
            new TicketMessagesTool($this->ticketId, $user)
        ];
    }
}
