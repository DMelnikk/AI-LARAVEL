<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Laravel\Ai\Ai;

class AiUsage extends Model
{
    protected $fillable = [
      'ai_run_id',
      'prompt_tokens',
      'completion_tokens',
      'total_tokens',
      'cost_usd',
    ];

    public function run(): BelongsTo
    {
        return $this->belongsTo(Ai::class,'ai_run_id');
    }
}
