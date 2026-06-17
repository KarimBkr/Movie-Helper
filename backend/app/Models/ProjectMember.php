<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectMember extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $fillable = ['project_id', 'user_id', 'role'];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}
