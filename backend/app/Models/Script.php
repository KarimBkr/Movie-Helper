<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Script extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $fillable = [
        'project_id',
        'uploaded_by',
        'file_name',
        'storage_bucket',
        'storage_path',
        'file_size_bytes',
        'parse_status',
        'parse_error',
        'sequence_count',
        'is_active',
        'parsed_at',
    ];

    protected $casts = [
        'file_size_bytes' => 'integer',
        'sequence_count' => 'integer',
        'is_active' => 'boolean',
        'parsed_at' => 'datetime',
        'created_at' => 'datetime',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function sequences(): HasMany
    {
        return $this->hasMany(Sequence::class);
    }
}
