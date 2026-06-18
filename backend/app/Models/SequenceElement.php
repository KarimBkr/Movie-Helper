<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Élément à dépouiller rattaché à une séquence.
 *
 * Soft delete (champ deleted_at — convention CLAUDE.md). Un élément en statut
 * 'ia' doit toujours avoir un source_text non vide (contrainte DB + règle 7).
 */
class SequenceElement extends Model
{
    use HasUuids;
    use SoftDeletes;

    protected $fillable = [
        'project_id',
        'sequence_id',
        'category',
        'value',
        'source_text',
        'confidence',
        'status',
        'note',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    public function sequence(): BelongsTo
    {
        return $this->belongsTo(Sequence::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}
