<?php

namespace App\Services\Storage;

use App\Contracts\FileStorage;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Implémentation FileStorage via l'API Storage REST de Supabase.
 *
 * Utilise la service_role key (règle 2 de CLAUDE.md : secret côté Laravel
 * uniquement) et bypass donc la RLS ; l'autorisation est vérifiée en amont
 * par ProjectAccessService.
 */
class SupabaseStorage implements FileStorage
{
    public function __construct(
        private readonly string $url,
        private readonly string $serviceRoleKey,
        private readonly int $timeoutSeconds = 30,
    ) {
        if ($this->url === '' || $this->serviceRoleKey === '') {
            throw new RuntimeException('Configuration Supabase Storage incomplète (url ou service_role_key manquant).');
        }
    }

    public function upload(string $bucket, string $path, string $contents, string $contentType): void
    {
        $response = $this->client()
            ->withBody($contents, $contentType)
            ->post($this->objectUrl($bucket, $path));

        if ($response->failed()) {
            throw new RuntimeException(
                "Échec de l'upload vers Supabase Storage ({$bucket}/{$path}) : HTTP {$response->status()}."
            );
        }
    }

    public function download(string $bucket, string $path): string
    {
        $response = $this->client()->get($this->objectUrl($bucket, $path));

        if ($response->failed()) {
            throw new RuntimeException(
                "Fichier introuvable dans Supabase Storage ({$bucket}/{$path}) : HTTP {$response->status()}."
            );
        }

        return $response->body();
    }

    public function delete(string $bucket, string $path): void
    {
        $response = $this->client()->delete($this->objectUrl($bucket, $path));

        // 404 = déjà absent → idempotent, on n'échoue pas.
        if ($response->failed() && $response->status() !== 404) {
            throw new RuntimeException(
                "Échec de la suppression dans Supabase Storage ({$bucket}/{$path}) : HTTP {$response->status()}."
            );
        }
    }

    private function client(): PendingRequest
    {
        return Http::withHeaders([
            'Authorization' => 'Bearer '.$this->serviceRoleKey,
            'apikey' => $this->serviceRoleKey,
        ])->timeout($this->timeoutSeconds);
    }

    private function objectUrl(string $bucket, string $path): string
    {
        return rtrim($this->url, '/').'/storage/v1/object/'.$bucket.'/'.ltrim($path, '/');
    }
}
