<?php

namespace App\Services;

use App\Models\Project;
use Illuminate\Support\Str;

class ProjectSlugService
{
    public function generate(string $title): string
    {
        do {
            $slug = Str::slug($title).'-'.Str::lower(Str::random(6));
        } while (Project::where('slug', $slug)->exists());

        return $slug;
    }
}
