<?php

namespace Tests\Feature;

use App\Jobs\AnalyzeSequenceJob;
use App\Models\AnalysisJob;
use App\Models\AnalysisJobItem;
use App\Models\Project;
use App\Models\ProjectMember;
use App\Models\Script;
use App\Models\Sequence;
use Firebase\JWT\JWT;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Tests des endpoints d'orchestration de l'analyse IA (L-06). La file est
 * simulée via Queue::fake() : on vérifie le dispatch, pas l'exécution des jobs.
 */
class AnalysisControllerTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'test-jwt-secret-longue-au-moins-32-chars';

    private const OWNER_ID = '11111111-1111-1111-1111-111111111111';

    private const VIEWER_ID = '22222222-2222-2222-2222-222222222222';

    private const STRANGER_ID = '33333333-3333-3333-3333-333333333333';

    protected function setUp(): void
    {
        parent::setUp();
        config(['supabase.jwt_secret' => self::SECRET]);
    }

    private function authHeader(string $userId): array
    {
        $token = JWT::encode([
            'sub' => $userId,
            'email' => $userId.'@test.com',
            'role' => 'authenticated',
            'exp' => time() + 3600,
            'iat' => time(),
            'aud' => 'authenticated',
        ], self::SECRET, 'HS256');

        return ['Authorization' => 'Bearer '.$token];
    }

    private function makeProject(): Project
    {
        $project = Project::create([
            'title' => 'Mon film',
            'slug' => 'mon-film-'.substr(self::OWNER_ID, 0, 6),
            'type' => 'film',
            'owner_id' => self::OWNER_ID,
        ]);

        ProjectMember::create([
            'project_id' => $project->id,
            'user_id' => self::VIEWER_ID,
            'role' => 'viewer',
        ]);

        return $project;
    }

    private function makeParsedScript(Project $project, int $sequences = 2): Script
    {
        $script = Script::create([
            'project_id' => $project->id,
            'uploaded_by' => self::OWNER_ID,
            'file_name' => 'scenario.fdx',
            'storage_bucket' => 'fdx-files',
            'storage_path' => "{$project->id}/script/original.fdx",
            'parse_status' => 'parsed',
            'sequence_count' => $sequences,
        ]);

        for ($i = 1; $i <= $sequences; $i++) {
            Sequence::create([
                'project_id' => $project->id,
                'script_id' => $script->id,
                'display_order' => $i,
                'int_ext' => 'INT',
                'day_night' => 'JOUR',
                'raw_text' => "INT. DECOR {$i} - JOUR\nAction.",
                'parse_status' => 'parsed',
                'status' => 'ia',
            ]);
        }

        return $script;
    }

    // --- start ---

    public function test_start_requires_auth(): void
    {
        $this->postJson('/api/projects/x/analysis/start')->assertStatus(401);
    }

    public function test_owner_can_start_analysis_and_jobs_are_dispatched(): void
    {
        Queue::fake();
        $project = $this->makeProject();
        $this->makeParsedScript($project, 2);

        $response = $this->postJson(
            "/api/projects/{$project->id}/analysis/start",
            [],
            $this->authHeader(self::OWNER_ID),
        );

        $response->assertStatus(201)
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.total_sequences', 2);

        $this->assertSame(2, AnalysisJobItem::count());
        Queue::assertPushed(AnalyzeSequenceJob::class, 2);
    }

    public function test_viewer_cannot_start(): void
    {
        Queue::fake();
        $project = $this->makeProject();
        $this->makeParsedScript($project);

        $this->postJson(
            "/api/projects/{$project->id}/analysis/start",
            [],
            $this->authHeader(self::VIEWER_ID),
        )->assertStatus(403)->assertJsonPath('code', 'FORBIDDEN_PROJECT');

        Queue::assertNothingPushed();
    }

    public function test_stranger_gets_not_found(): void
    {
        $project = $this->makeProject();
        $this->makeParsedScript($project);

        $this->postJson(
            "/api/projects/{$project->id}/analysis/start",
            [],
            $this->authHeader(self::STRANGER_ID),
        )->assertStatus(404)->assertJsonPath('code', 'PROJECT_NOT_FOUND');
    }

    public function test_start_conflicts_when_analysis_already_running(): void
    {
        Queue::fake();
        $project = $this->makeProject();
        $script = $this->makeParsedScript($project);

        AnalysisJob::create([
            'project_id' => $project->id,
            'script_id' => $script->id,
            'requested_by' => self::OWNER_ID,
            'status' => 'running',
            'total_sequences' => 2,
        ]);

        $this->postJson(
            "/api/projects/{$project->id}/analysis/start",
            [],
            $this->authHeader(self::OWNER_ID),
        )->assertStatus(409)->assertJsonPath('code', 'ANALYSIS_ALREADY_RUNNING');

        Queue::assertNothingPushed();
    }

    public function test_start_requires_parsed_script(): void
    {
        Queue::fake();
        $project = $this->makeProject();
        Script::create([
            'project_id' => $project->id,
            'uploaded_by' => self::OWNER_ID,
            'file_name' => 'scenario.fdx',
            'storage_bucket' => 'fdx-files',
            'storage_path' => "{$project->id}/script/original.fdx",
            'parse_status' => 'uploaded',
        ]);

        $this->postJson(
            "/api/projects/{$project->id}/analysis/start",
            [],
            $this->authHeader(self::OWNER_ID),
        )->assertStatus(422)->assertJsonPath('code', 'INVALID_FDX_FILE');

        Queue::assertNothingPushed();
    }

    public function test_start_without_script_returns_not_found(): void
    {
        $project = $this->makeProject();

        $this->postJson(
            "/api/projects/{$project->id}/analysis/start",
            [],
            $this->authHeader(self::OWNER_ID),
        )->assertStatus(404)->assertJsonPath('code', 'SCRIPT_NOT_FOUND');
    }

    // --- show ---

    public function test_member_can_view_progress(): void
    {
        $project = $this->makeProject();
        $script = $this->makeParsedScript($project);
        $job = AnalysisJob::create([
            'project_id' => $project->id,
            'script_id' => $script->id,
            'requested_by' => self::OWNER_ID,
            'status' => 'running',
            'total_sequences' => 2,
            'processed_sequences' => 1,
        ]);

        // Un viewer peut suivre la progression.
        $this->getJson(
            "/api/projects/{$project->id}/analysis/{$job->id}",
            $this->authHeader(self::VIEWER_ID),
        )->assertStatus(200)
            ->assertJsonPath('data.status', 'running')
            ->assertJsonPath('data.processed_sequences', 1);
    }

    public function test_show_unknown_job_returns_not_found(): void
    {
        $project = $this->makeProject();

        $this->getJson(
            "/api/projects/{$project->id}/analysis/44444444-4444-4444-4444-444444444444",
            $this->authHeader(self::OWNER_ID),
        )->assertStatus(404)->assertJsonPath('code', 'ANALYSIS_NOT_FOUND');
    }

    // --- retry-failed ---

    public function test_owner_can_retry_failed_items(): void
    {
        Queue::fake();
        $project = $this->makeProject();
        $script = $this->makeParsedScript($project, 2);
        $sequences = Sequence::where('script_id', $script->id)->orderBy('display_order')->get();

        $job = AnalysisJob::create([
            'project_id' => $project->id,
            'script_id' => $script->id,
            'requested_by' => self::OWNER_ID,
            'status' => 'partial',
            'total_sequences' => 2,
            'processed_sequences' => 1,
            'failed_sequences' => 1,
        ]);

        AnalysisJobItem::create([
            'project_id' => $project->id, 'analysis_job_id' => $job->id,
            'sequence_id' => $sequences[0]->id, 'status' => 'completed',
        ]);
        AnalysisJobItem::create([
            'project_id' => $project->id, 'analysis_job_id' => $job->id,
            'sequence_id' => $sequences[1]->id, 'status' => 'failed', 'error_message' => 'boom',
        ]);

        $this->postJson(
            "/api/projects/{$project->id}/analysis/{$job->id}/retry-failed",
            [],
            $this->authHeader(self::OWNER_ID),
        )->assertStatus(200)
            ->assertJsonPath('data.status', 'running')
            ->assertJsonPath('data.failed_sequences', 0);

        // Seul l'item échoué est relancé.
        Queue::assertPushed(AnalyzeSequenceJob::class, 1);
        $this->assertSame(0, AnalysisJobItem::where('status', 'failed')->count());
    }

    public function test_viewer_cannot_retry(): void
    {
        Queue::fake();
        $project = $this->makeProject();
        $script = $this->makeParsedScript($project);
        $job = AnalysisJob::create([
            'project_id' => $project->id,
            'script_id' => $script->id,
            'requested_by' => self::OWNER_ID,
            'status' => 'partial',
            'total_sequences' => 2,
        ]);

        $this->postJson(
            "/api/projects/{$project->id}/analysis/{$job->id}/retry-failed",
            [],
            $this->authHeader(self::VIEWER_ID),
        )->assertStatus(403)->assertJsonPath('code', 'FORBIDDEN_PROJECT');

        Queue::assertNothingPushed();
    }
}
