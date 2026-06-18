<?php

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * Exception métier rendue au format d'erreur API du projet :
 * { "message": "...", "code": "ERROR_CODE", "errors": {} } (en français).
 *
 * Évite de répéter response()->json([...], 4xx) dans chaque contrôleur.
 *
 * @param  array<string,mixed>  $errors
 */
class ApiException extends RuntimeException
{
    /**
     * @param  array<string,mixed>  $errors
     */
    public function __construct(
        string $message,
        public readonly string $errorCode,
        public readonly int $statusCode,
        public readonly array $errors = [],
    ) {
        parent::__construct($message);
    }

    public function render(Request $request): JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage(),
            'code' => $this->errorCode,
            'errors' => (object) $this->errors,
        ], $this->statusCode);
    }
}
