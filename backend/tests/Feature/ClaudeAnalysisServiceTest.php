<?php

namespace Tests\Feature;

use App\Exceptions\ClaudeException;
use App\Services\Claude\ClaudeAnalysisService;
use App\Services\Claude\SequenceBreakdownTool;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Tests de l'appel Claude (L-04). L'API Anthropic est simulée via Http::fake() :
 * aucun appel réseau réel, aucune clé réelle. On vérifie le contrat tool_use
 * sortant (règle 3) et la lecture stricte du bloc tool_use entrant.
 */
class ClaudeAnalysisServiceTest extends TestCase
{
    private function service(): ClaudeAnalysisService
    {
        return new ClaudeAnalysisService(
            apiKey: 'test-key',
            model: 'claude-sonnet-4-6',
            maxTokens: 4000,
            timeoutSeconds: 30,
            baseUrl: 'https://api.anthropic.com',
            version: '2023-06-01',
        );
    }

    private function toolUseResponse(array $input): array
    {
        return [
            'id' => 'msg_1',
            'type' => 'message',
            'role' => 'assistant',
            'content' => [
                ['type' => 'tool_use', 'id' => 'tu_1', 'name' => SequenceBreakdownTool::NAME, 'input' => $input],
            ],
            'stop_reason' => 'tool_use',
        ];
    }

    private function validInput(): array
    {
        return [
            'sequence' => [
                'scene_number' => '12',
                'resume' => ['value' => 'Marc entre.', 'source_text' => 'Marc entre.', 'confidence' => 'high'],
                'decor' => ['value' => 'Salon', 'source_text' => 'INT. SALON', 'confidence' => 'high'],
                'int_ext' => ['value' => 'INT', 'source_text' => 'INT.', 'confidence' => 'high'],
                'jour_nuit' => ['value' => 'JOUR', 'source_text' => 'JOUR', 'confidence' => 'high'],
                'huitiemes' => ['value' => 2, 'source_text' => '', 'confidence' => 'medium'],
            ],
            'elements' => [
                ['category' => 'accessoire', 'value' => 'revolver', 'source_text' => 'le revolver', 'confidence' => 'high', 'note' => ''],
            ],
            'flags' => [],
            'notes' => [],
        ];
    }

    public function test_sends_forced_tool_choice_with_full_schema(): void
    {
        Http::fake([
            'api.anthropic.com/*' => Http::response($this->toolUseResponse($this->validInput())),
        ]);

        $this->service()->analyzeSequence('INT. SALON - JOUR\nMarc pose le revolver.');

        Http::assertSent(function (Request $request) {
            $body = $request->data();

            return $request->url() === 'https://api.anthropic.com/v1/messages'
                && $request->hasHeader('x-api-key', 'test-key')
                && $request->hasHeader('anthropic-version', '2023-06-01')
                && $body['model'] === 'claude-sonnet-4-6'
                && $body['tool_choice'] === ['type' => 'tool', 'name' => 'extract_sequence_breakdown']
                && $body['tools'][0]['name'] === 'extract_sequence_breakdown'
                && ! empty($body['tools'][0]['input_schema']['properties']);
        });
    }

    public function test_returns_typed_breakdown_from_tool_use_block(): void
    {
        Http::fake([
            'api.anthropic.com/*' => Http::response($this->toolUseResponse($this->validInput())),
        ]);

        $breakdown = $this->service()->analyzeSequence('texte');

        $this->assertSame('12', $breakdown->sceneNumber);
        $this->assertSame('INT', $breakdown->field('int_ext')->value);
        $this->assertCount(1, $breakdown->elements);
    }

    public function test_ignores_free_text_and_reads_only_tool_use(): void
    {
        $response = [
            'content' => [
                ['type' => 'text', 'text' => '{"sequence": "ceci est du JSON libre à ignorer"}'],
                ['type' => 'tool_use', 'name' => SequenceBreakdownTool::NAME, 'input' => $this->validInput()],
            ],
        ];
        Http::fake(['api.anthropic.com/*' => Http::response($response)]);

        $breakdown = $this->service()->analyzeSequence('texte');

        $this->assertSame('12', $breakdown->sceneNumber);
    }

    public function test_throws_when_no_tool_use_block(): void
    {
        Http::fake([
            'api.anthropic.com/*' => Http::response([
                'content' => [['type' => 'text', 'text' => 'pas de tool_use ici']],
            ]),
        ]);

        $this->expectException(ClaudeException::class);
        $this->expectExceptionMessageMatches('/CLAUDE|analyse IA/i');

        $this->service()->analyzeSequence('texte');
    }

    public function test_throws_on_wrong_tool_name(): void
    {
        Http::fake([
            'api.anthropic.com/*' => Http::response([
                'content' => [['type' => 'tool_use', 'name' => 'autre_outil', 'input' => []]],
            ]),
        ]);

        $this->expectException(ClaudeException::class);

        $this->service()->analyzeSequence('texte');
    }

    public function test_throws_on_http_error(): void
    {
        Http::fake([
            'api.anthropic.com/*' => Http::response(['error' => 'overloaded'], 529),
        ]);

        $this->expectException(ClaudeException::class);

        $this->service()->analyzeSequence('texte');
    }

    public function test_throws_when_api_key_missing(): void
    {
        $service = new ClaudeAnalysisService(
            apiKey: null,
            model: 'claude-sonnet-4-6',
            maxTokens: 4000,
            timeoutSeconds: 30,
            baseUrl: 'https://api.anthropic.com',
            version: '2023-06-01',
        );

        Http::fake();

        $this->expectException(ClaudeException::class);

        $service->analyzeSequence('texte');

        Http::assertNothingSent();
    }
}
