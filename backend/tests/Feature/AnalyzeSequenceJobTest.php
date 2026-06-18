<?php

namespace Tests\Feature;

use App\Actions\PersistBreakdownAction;
use App\Jobs\AnalyzeSequenceJob;
use App\Models\AnalysisJob;
use App\Models\AnalysisJobItem;
use App\Models\Project;
use App\Models\Script;
use App\Models\Sequence;
use App\Models\SequenceElement;
use App\Services\Claude\ClaudeAnalysisService;
use App\Services\Claude\SequenceBreakdownTool;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Tests du job d'analyse IA d'une séquence (L-05). L'API Anthropic est simulée
 * via Http::fake() ; le job est exécuté en synchrone (handle/failed appelés
 * directement) sur la base de test Postgres.
 */
class AnalyzeSequenceJobTest extends TestCase
{
    use RefreshDatabase;

    private const OWNER_ID = '11111111-1111-1111-1111-111111111111';

    private function toolUseResponse(array $input): array
    {
        return [
            'content' => [
                ['type' => 'tool_use', 'id' => 'toolu_abc', 'name' => SequenceBreakdownTool::NAME, 'input' => $input],
            ],
        ];
    }

    private function breakdownInput(?array $elements = null): array
    {
        return [
            'sequence' => [
                'scene_number' => '12',
                'resume' => ['value' => 'Marc pose le revolver.', 'source_text' => 'Marc pose le revolver.', 'confidence' => 'high'],
                'decor' => ['value' => 'Salon', 'source_text' => 'INT. SALON', 'confidence' => 'high'],
                'int_ext' => ['value' => 'INT', 'source_text' => 'INT.', 'confidence' => 'high'],
                'jour_nuit' => ['value' => 'JOUR', 'source_text' => 'JOUR', 'confidence' => 'high'],
                'huitiemes' => ['value' => 3, 'source_text' => '', 'confidence' => 'medium'],
            ],
            'elements' => $elements ?? [
                ['category' => 'accessoire', 'value' => 'revolver', 'source_text' => 'le revolver', 'confidence' => 'high', 'note' => ''],
            ],
            'flags' => [],
            'notes' => [],
        ];
    }

    /**
     * Seed un projet → script → séquence → job → item « pending ».
     */
    private function seedItem(): AnalysisJobItem
    {
        $project = Project::create([
            'title' => 'Mon film',
            'slug' => 'mon-film-aaaaaa',
            'type' => 'film',
            'owner_id' => self::OWNER_ID,
        ]);

        $script = Script::create([
            'project_id' => $project->id,
            'uploaded_by' => self::OWNER_ID,
            'file_name' => 'scenario.fdx',
            'storage_bucket' => 'fdx-files',
            'storage_path' => "{$project->id}/script/original.fdx",
            'parse_status' => 'parsed',
        ]);

        $sequence = Sequence::create([
            'project_id' => $project->id,
            'script_id' => $script->id,
            'display_order' => 1,
            'scene_number' => '12',
            'int_ext' => 'INT',
            'day_night' => 'JOUR',
            'decor' => 'Salon',
            'raw_text' => "INT. SALON - JOUR\nMarc pose le revolver sur la table.",
            'parse_status' => 'parsed',
            'status' => 'ia',
        ]);

        $job = AnalysisJob::create([
            'project_id' => $project->id,
            'script_id' => $script->id,
            'requested_by' => self::OWNER_ID,
            'status' => 'pending',
            'total_sequences' => 1,
        ]);

        return AnalysisJobItem::create([
            'project_id' => $project->id,
            'analysis_job_id' => $job->id,
            'sequence_id' => $sequence->id,
            'status' => 'pending',
        ]);
    }

    public function test_successful_run_persists_elements_and_completes_job(): void
    {
        Http::fake(['api.anthropic.com/*' => Http::response($this->toolUseResponse($this->breakdownInput()))]);

        $item = $this->seedItem();

        (new AnalyzeSequenceJob($item->id))->handle(
            app(ClaudeAnalysisService::class),
            app(PersistBreakdownAction::class),
        );

        $item->refresh();
        $this->assertSame('completed', $item->status);
        $this->assertSame('toolu_abc', $item->claude_tool_use_id);
        $this->assertSame(1, $item->attempts);
        $this->assertNotNull($item->raw_tool_input);

        // Élément IA persisté avec source_text (règle 7).
        $this->assertDatabaseHas('sequence_elements', [
            'sequence_id' => $item->sequence_id,
            'category' => 'accessoire',
            'value' => 'revolver',
            'status' => 'ia',
        ]);

        // Champs IA de la séquence renseignés (resume + huitiemes).
        $sequence = Sequence::find($item->sequence_id);
        $this->assertSame('Marc pose le revolver.', $sequence->resume);
        $this->assertSame('3.00', $sequence->huitiemes);

        // Job recalculé : 1/1 traité, statut completed.
        $job = AnalysisJob::find($item->analysis_job_id);
        $this->assertSame('completed', $job->status);
        $this->assertSame(1, $job->processed_sequences);
        $this->assertSame(0, $job->failed_sequences);
        $this->assertNotNull($job->finished_at);
    }

    public function test_elements_without_source_text_are_not_persisted(): void
    {
        $elements = [
            ['category' => 'accessoire', 'value' => 'revolver', 'source_text' => 'le revolver', 'confidence' => 'high', 'note' => ''],
            ['category' => 'accessoire', 'value' => 'couteau', 'source_text' => '', 'confidence' => 'low', 'note' => ''],
        ];
        Http::fake(['api.anthropic.com/*' => Http::response($this->toolUseResponse($this->breakdownInput($elements)))]);

        $item = $this->seedItem();

        (new AnalyzeSequenceJob($item->id))->handle(
            app(ClaudeAnalysisService::class),
            app(PersistBreakdownAction::class),
        );

        $this->assertSame(1, SequenceElement::where('sequence_id', $item->sequence_id)->count());
        $this->assertDatabaseMissing('sequence_elements', ['value' => 'couteau']);
    }

    public function test_reanalysis_replaces_only_ia_elements(): void
    {
        Http::fake(['api.anthropic.com/*' => Http::response($this->toolUseResponse($this->breakdownInput()))]);

        $item = $this->seedItem();

        // Un élément déjà validé par un humain doit survivre au re-dépouillement.
        SequenceElement::create([
            'project_id' => $item->project_id,
            'sequence_id' => $item->sequence_id,
            'category' => 'personnage',
            'value' => 'Marc',
            'source_text' => 'Marc',
            'confidence' => 'high',
            'status' => 'verifie',
        ]);

        (new AnalyzeSequenceJob($item->id))->handle(
            app(ClaudeAnalysisService::class),
            app(PersistBreakdownAction::class),
        );

        $this->assertDatabaseHas('sequence_elements', ['value' => 'Marc', 'status' => 'verifie']);
        $this->assertDatabaseHas('sequence_elements', ['value' => 'revolver', 'status' => 'ia']);
    }

    public function test_failed_marks_item_and_job_failed(): void
    {
        $item = $this->seedItem();

        (new AnalyzeSequenceJob($item->id))->failed(new \RuntimeException('Claude indisponible'));

        $item->refresh();
        $this->assertSame('failed', $item->status);
        $this->assertStringContainsString('Claude indisponible', $item->error_message);

        $job = AnalysisJob::find($item->analysis_job_id);
        $this->assertSame('failed', $job->status);
        $this->assertSame(1, $job->failed_sequences);
        $this->assertNotNull($job->finished_at);
    }

    public function test_job_uses_ai_queue_tries_and_backoff(): void
    {
        config(['queue.ai.queue' => 'ai', 'queue.ai.tries' => 2, 'queue.ai.backoff' => 30]);

        $job = new AnalyzeSequenceJob('some-id');

        $this->assertSame('ai', $job->queue);
        $this->assertSame(2, $job->tries);
        $this->assertSame(30, $job->backoff);
    }
}
