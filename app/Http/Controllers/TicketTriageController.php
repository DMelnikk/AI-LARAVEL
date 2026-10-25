<?php

namespace App\Http\Controllers;

use App\Ai\Agents\TicketTriager;
use App\Models\AiRun;
use App\Models\AiUsage;
use App\Models\Ticket;
use Illuminate\Http\Request;

class TicketTriageController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request, Ticket $ticket)
    {

        $run = AiRun::create([
            'team_id' => $ticket->team_id,
            'user_id' => $request->user()->id,
            'ticket_id' => $ticket->id,
            'feature_key' => 'ticket_triage',
            'status' => 'running',
            'provider' => 'gemini',
            'model' => null,
            'input_hash' => sha1($ticket->subject.'|'.$ticket->messages()->latest()->first()?->body),
            'started_at' => now(),
        ]);

        try {
            $response = (new TicketTriager)->prompt(
                "Subject {$ticket->subject}\n\n{$ticket->messages()->latest()->first()?->body}"
            );



            $ticket->update([
                'priority' => $response['priority'],
                'department' => $response['department'],
                'sentiment' => $response['sentiment'],
                'ai_tags' => $response['tags'],
            ]);

            if (! empty($response['summary'])) {
                $ticket->messages()->create([
                    'user_id' => null,
                    'role' => 'system',
                    'body' => 'AI Summary: '.$response['summary'],
                ]);
            }

            $run->update([
               'status' => 'succeeded',
                'finished_at' => now(),
            ]);

            if(isset($response->usage)) {
                $usage = $response->usage;

                AiUsage::create([
                    'ai_run_id' => $run->id,
                    'prompt_tokens' => $usage->promptTokens ?? 0,
                    'completion_tokens' => $usage->completionTokens ?? 0,
                    'total_tokens' => $usage->totalTokens ?? 0,
                    'cost_usd' => $usage->costUsd ?? 0,
                ]);
            }

            return response()->json([
                'status' => 'ok',
                'data' => $response,
            ]);

        } catch (\Throwable $e) {
            $run->update([
                'status' => 'failed',
                'finished_at' => now(),
                'error_message' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
