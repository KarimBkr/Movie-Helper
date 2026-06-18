<?php

namespace App\Actions;

use App\Contracts\FileStorage;
use App\Exceptions\ScriptException;
use App\Models\Script;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Upload d'un scénario FDX (L-01).
 *
 * Crée la ligne `scripts`, puis pousse le fichier dans Storage au chemin
 * {project_id}/{script_id}/original.fdx (le project_id en tête est requis par
 * les policies RLS Supabase). Tout est transactionnel : si l'upload échoue,
 * la ligne scripts est annulée.
 */
class UploadScriptAction
{
    public function __construct(private readonly FileStorage $storage) {}

    public function execute(string $projectId, string $userId, UploadedFile $file): Script
    {
        $contents = $file->get();

        // Garde-fou déterministe minimal : un FDX est un XML FinalDraft.
        // Le parsing fin reste à L-03 ; ici on rejette juste l'évidence.
        if (! str_contains($contents, '<FinalDraft')) {
            throw ScriptException::invalidFdx('Le fichier ne semble pas être un scénario Final Draft (.fdx).');
        }

        $bucket = config('supabase.storage.fdx_bucket');

        // L'ID est généré en amont pour bâtir le chemin {project_id}/{script_id}/…
        // avant l'INSERT (la contrainte DB interdit un storage_path vide).
        $scriptId = (string) Str::uuid();
        $path = "{$projectId}/{$scriptId}/original.fdx";

        return DB::transaction(function () use ($scriptId, $projectId, $userId, $file, $contents, $bucket, $path): Script {
            $script = Script::create([
                'id' => $scriptId,
                'project_id' => $projectId,
                'uploaded_by' => $userId,
                'file_name' => $file->getClientOriginalName(),
                'storage_bucket' => $bucket,
                'storage_path' => $path,
                'file_size_bytes' => $file->getSize(),
                'parse_status' => 'uploaded',
            ]);

            // Si l'upload échoue, l'exception fait rollback de la ligne scripts.
            $this->storage->upload($bucket, $path, $contents, 'application/xml');

            return $script;
        });
    }
}
