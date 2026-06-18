<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Sequence extends Model
{
    use HasUuids;

    protected $fillable = [
        'project_id',
        'script_id',
        'display_order',
        'scene_number',
        'scene_heading',
        'decor',
        'sub_decor',
        'int_ext',
        'day_night',
        'raw_text',
        'resume',
        'huitiemes',
        'parse_status',
        'status',
        'flags',
        'validated_at',
        'validated_by',
    ];

    protected $casts = [
        'display_order' => 'integer',
        'huitiemes' => 'decimal:2',
        'flags' => 'array',
        'validated_at' => 'datetime',
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
}
