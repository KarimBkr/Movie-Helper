<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Job d'analyse IA d'un script : suit la progression globale de l'analyse
 * séquence par séquence (statuts analysis_job_status).
 */
class AnalysisJob extends Model
{
    use HasUuids;

    protected $fillable = [
        'project_id',
        'script_id',
        'requested_by',
        'status',
        'total_sequences',
        'processed_sequences',
        'failed_sequences',
        'error_message',
        'started_at',
        'finished_at',
    ];

    protected $casts = [
        'total_sequences' => 'integer',
        'processed_sequences' => 'integer',
        'failed_sequences' => 'integer',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function script(): BelongsTo
    {
        return $this->belongsTo(Script::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(AnalysisJobItem::class);
    }
}
