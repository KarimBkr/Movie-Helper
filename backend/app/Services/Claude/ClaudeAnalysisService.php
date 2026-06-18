<?php

namespace App\Services\Claude;

use App\DTOs\Claude\SequenceBreakdown;
use App\Exceptions\ClaudeException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Appel Claude pour le pré-dépouillement d'UNE séquence (L-04).
 *
 * Respecte strictement la règle 3 de CLAUDE.md :
 *   - tool_choice forcé sur extract_sequence_breakdown,
 *   - input_schema COMPLET injecté (SequenceBreakdownTool::definition()),
 *   - on ne lit QUE content[type=tool_use].input ; le texte libre est ignoré.
 *
 * En cas d'échec (HTTP, pas de tool_use, mauvais outil) → ClaudeException
 * (CLAUDE_TOOL_USE_FAILED). Le service est sans état : il ne touche pas la DB.
 */
class ClaudeAnalysisService
{
    public function __construct(
        private readonly ?string $apiKey,
        private readonly string $model,
        private readonly int $maxTokens,
        private readonly int $timeoutSeconds,
        private readonly string $baseUrl,
        private readonly string $version,
    ) {}

    /**
     * Analyse le texte d'une séquence et renvoie le dépouillement validé.
     *
     * @throws ClaudeException
     */
    public function analyzeSequence(string $sequenceText): SequenceBreakdown
    {
        if ($this->apiKey === null || $this->apiKey === '') {
            throw ClaudeException::notConfigured();
        }

        $response = $this->send($this->buildPayload($sequenceText));
        [$toolUseId, $input] = $this->extractToolUse($response);

        return SequenceBreakdown::fromToolInput($input, $toolUseId);
    }

    /**
     * @param  array<string,mixed>  $payload
     * @return array<string,mixed>
     *
     * @throws ClaudeException
     */
    private function send(array $payload): array
    {
        try {
            $response = $this->client()->post(
                rtrim($this->baseUrl, '/').'/v1/messages',
                $payload,
            );
        } catch (Throwable $e) {
            throw ClaudeException::toolUseFailed('appel API impossible ('.$e->getMessage().').');
        }

        if ($response->failed()) {
            throw ClaudeException::toolUseFailed("réponse HTTP {$response->status()} de l'API Anthropic.");
        }

        $json = $response->json();

        if (! is_array($json)) {
            throw ClaudeException::toolUseFailed('corps de réponse Anthropic illisible.');
        }

        return $json;
    }

    /**
     * Trouve le bloc tool_use du bon outil et renvoie [id, input].
     * Aucun fallback sur du JSON libre (règle 3 de CLAUDE.md).
     *
     * @param  array<string,mixed>  $response
     * @return array{0:?string,1:array<string,mixed>}
     *
     * @throws ClaudeException
     */
    private function extractToolUse(array $response): array
    {
        $content = $response['content'] ?? null;

        if (! is_array($content)) {
            throw ClaudeException::toolUseFailed('réponse sans bloc content.');
        }

        foreach ($content as $block) {
            if (! is_array($block)) {
                continue;
            }

            if (($block['type'] ?? null) !== 'tool_use') {
                continue;
            }

            if (($block['name'] ?? null) !== SequenceBreakdownTool::NAME) {
                continue;
            }

            $input = $block['input'] ?? null;

            if (! is_array($input)) {
                throw ClaudeException::toolUseFailed('bloc tool_use sans input exploitable.');
            }

            $id = isset($block['id']) ? (string) $block['id'] : null;

            return [$id, $input];
        }

        throw ClaudeException::toolUseFailed('aucun bloc tool_use '.SequenceBreakdownTool::NAME.' dans la réponse.');
    }

    /**
     * Construit le payload de l'API Messages avec tool_choice forcé.
     *
     * @return array<string,mixed>
     */
    private function buildPayload(string $sequenceText): array
    {
        return [
            'model' => $this->model,
            'max_tokens' => $this->maxTokens,
            'system' => $this->systemPrompt(),
            'tools' => [SequenceBreakdownTool::definition()],
            'tool_choice' => [
                'type' => 'tool',
                'name' => SequenceBreakdownTool::NAME,
            ],
            'messages' => [
                [
                    'role' => 'user',
                    'content' => "Texte de la séquence à dépouiller :\n\n".$sequenceText,
                ],
            ],
        ];
    }

    private function systemPrompt(): string
    {
        return implode(' ', [
            'Tu es un assistant de pré-dépouillement de scénario pour la production cinéma.',
            'Tu analyses UNE séquence et tu extrais son dépouillement via l\'outil',
            'extract_sequence_breakdown. Principe : l\'IA propose, l\'assistant décide.',
            'N\'invente jamais un élément absent du texte. Chaque élément et chaque champ',
            'doit être justifié par un source_text littéralement présent dans la séquence.',
            'Si une information manque, laisse-la vide ou signale-la dans flags plutôt que de la deviner.',
        ]);
    }

    private function client(): PendingRequest
    {
        return Http::withHeaders([
            'x-api-key' => $this->apiKey,
            'anthropic-version' => $this->version,
            'content-type' => 'application/json',
        ])->timeout($this->timeoutSeconds);
    }
}
