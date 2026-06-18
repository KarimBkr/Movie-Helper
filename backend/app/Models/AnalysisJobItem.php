<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Une unité de travail de l'analyse IA : l'analyse d'UNE séquence dans le cadre
 * d'un AnalysisJob. Conserve l'id de tool_use et l'input brut renvoyé par Claude
 * (raw_tool_input) pour traçabilité et re-traitement.
 */
class AnalysisJobItem extends Model
{
    use HasUuids;

    protected $fillable = [
        'project_id',
        'analysis_job_id',
        'sequence_id',
        'status',
        'attempts',
        'error_message',
        'claude_tool_use_id',
        'raw_tool_input',
        'started_at',
        'finished_at',
    ];

    protected $casts = [
        'attempts' => 'integer',
        'raw_tool_input' => 'array',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function job(): BelongsTo
    {
        return $this->belongsTo(AnalysisJob::class, 'analysis_job_id');
    }

    public function sequence(): BelongsTo
    {
        return $this->belongsTo(Sequence::class);
    }
}
