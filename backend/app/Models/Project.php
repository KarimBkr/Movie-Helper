<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Project extends Model
{
    use HasUuids;

    protected $fillable = ['title', 'slug', 'type', 'owner_id'];

    public function members(): HasMany
    {
        return $this->hasMany(ProjectMember::class);
    }
}
