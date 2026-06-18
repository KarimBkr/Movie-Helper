<?php

namespace App\Contracts;

/**
 * Contrat d'accès au stockage de fichiers (Supabase Storage en prod).
 *
 * Abstrait derrière une interface pour pouvoir mocker en test sans toucher
 * au réseau ni au vrai bucket. Laravel reste la seule source de vérité pour
 * les écritures (règle 1 de CLAUDE.md) : l'accès est gouverné côté app par
 * ProjectAccessService avant tout appel ici.
 */
interface FileStorage
{
    /**
     * Téléverse le contenu d'un fichier dans le bucket au chemin donné.
     *
     * Le chemin commence toujours par {project_id} (policies RLS Supabase).
     *
     * @throws \RuntimeException si le stockage échoue.
     */
    public function upload(string $bucket, string $path, string $contents, string $contentType): void;

    /**
     * Récupère le contenu d'un fichier.
     *
     * @throws \RuntimeException si le fichier est introuvable ou illisible.
     */
    public function download(string $bucket, string $path): string;

    /**
     * Supprime un fichier (idempotent : ne lève pas si déjà absent).
     */
    public function delete(string $bucket, string $path): void;
}
