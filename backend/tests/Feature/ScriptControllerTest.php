<?php

namespace Tests\Feature;

use App\Contracts\FileStorage;
use App\Models\Project;
use App\Models\ProjectMember;
use App\Models\Script;
use App\Models\Sequence;
use Firebase\JWT\JWT;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Mockery;
use Tests\TestCase;

class ScriptControllerTest extends TestCase
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

    /**
     * Crée un projet dont OWNER_ID est propriétaire (le trigger DB crée
     * automatiquement le membership owner) et ajoute VIEWER_ID en viewer.
     */
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

    private function fakeFdx(): string
    {
        return <<<'XML'
        <?xml version="1.0" encoding="UTF-8"?>
        <FinalDraft DocumentType="Script" Version="5">
          <Content>
            <Paragraph Number="1" Type="Scene Heading"><Text>INT. SALON - JOUR</Text></Paragraph>
            <Paragraph Type="Action"><Text>Marc entre.</Text></Paragraph>
            <Paragraph Number="2" Type="Scene Heading"><Text>EXT. RUE - NUIT</Text></Paragraph>
            <Paragraph Type="Action"><Text>Une voiture demarre.</Text></Paragraph>
          </Content>
        </FinalDraft>
        XML;
    }

    private function uploadedFdx(?string $contents = null): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('scenario.fdx', $contents ?? $this->fakeFdx());
    }

    // --- Upload (L-01) ---

    public function test_upload_requires_auth(): void
    {
        $this->postJson('/api/projects/some-id/scripts/upload')->assertStatus(401);
    }

    public function test_owner_can_upload_fdx(): void
    {
        $project = $this->makeProject();

        $storage = Mockery::mock(FileStorage::class);
        $storage->shouldReceive('upload')->once();
        $this->app->instance(FileStorage::class, $storage);

        $response = $this->postJson(
            "/api/projects/{$project->id}/scripts/upload",
            ['file' => $this->uploadedFdx()],
            $this->authHeader(self::OWNER_ID),
        );

        $response->assertStatus(201)
            ->assertJsonPath('data.parse_status', 'uploaded')
            ->assertJsonPath('data.file_name', 'scenario.fdx');

        $this->assertDatabaseHas('scripts', [
            'project_id' => $project->id,
            'uploaded_by' => self::OWNER_ID,
            'parse_status' => 'uploaded',
        ]);
    }

    public function test_uploaded_path_starts_with_project_id(): void
    {
        $project = $this->makeProject();

        $storage = Mockery::mock(FileStorage::class);
        $storage->shouldReceive('upload')
            ->once()
            ->with('fdx-files', Mockery::pattern("#^{$project->id}/.+/original\.fdx$#"), Mockery::any(), 'application/xml');
        $this->app->instance(FileStorage::class, $storage);

        $this->postJson(
            "/api/projects/{$project->id}/scripts/upload",
            ['file' => $this->uploadedFdx()],
            $this->authHeader(self::OWNER_ID),
        )->assertStatus(201);
    }

    public function test_viewer_cannot_upload(): void
    {
        $project = $this->makeProject();

        $this->postJson(
            "/api/projects/{$project->id}/scripts/upload",
            ['file' => $this->uploadedFdx()],
            $this->authHeader(self::VIEWER_ID),
        )->assertStatus(403)->assertJsonPath('code', 'FORBIDDEN_PROJECT');
    }

    public function test_stranger_gets_not_found(): void
    {
        $project = $this->makeProject();

        $this->postJson(
            "/api/projects/{$project->id}/scripts/upload",
            ['file' => $this->uploadedFdx()],
            $this->authHeader(self::STRANGER_ID),
        )->assertStatus(404)->assertJsonPath('code', 'PROJECT_NOT_FOUND');
    }

    public function test_upload_rejects_missing_file(): void
    {
        $project = $this->makeProject();

        $this->postJson(
            "/api/projects/{$project->id}/scripts/upload",
            [],
            $this->authHeader(self::OWNER_ID),
        )->assertStatus(422)->assertJsonValidationErrors(['file']);
    }

    public function test_upload_rejects_non_finaldraft_content(): void
    {
        $project = $this->makeProject();

        $this->app->instance(FileStorage::class, Mockery::mock(FileStorage::class));

        $this->postJson(
            "/api/projects/{$project->id}/scripts/upload",
            ['file' => UploadedFile::fake()->createWithContent('fake.fdx', '<NotAScript/>')],
            $this->authHeader(self::OWNER_ID),
        )->assertStatus(422)->assertJsonPath('code', 'INVALID_FDX_FILE');
    }

    // --- Parse (L-03) ---

    public function test_owner_can_parse_and_sequences_are_created(): void
    {
        $project = $this->makeProject();
        $script = $this->seedUploadedScript($project);

        $storage = Mockery::mock(FileStorage::class);
        $storage->shouldReceive('download')->once()->andReturn($this->fakeFdx());
        $this->app->instance(FileStorage::class, $storage);

        $response = $this->postJson(
            "/api/projects/{$project->id}/scripts/{$script->id}/parse",
            [],
            $this->authHeader(self::OWNER_ID),
        );

        $response->assertStatus(200)
            ->assertJsonPath('data.parse_status', 'parsed')
            ->assertJsonPath('data.sequence_count', 2);

        $this->assertSame(2, Sequence::where('script_id', $script->id)->count());
        $this->assertDatabaseHas('sequences', [
            'script_id' => $script->id,
            'display_order' => 1,
            'int_ext' => 'INT',
            'day_night' => 'JOUR',
        ]);
    }

    public function test_parse_is_idempotent_then_conflicts_when_already_parsed(): void
    {
        $project = $this->makeProject();
        $script = $this->seedUploadedScript($project);
        $script->update(['parse_status' => 'parsed']);

        $this->app->instance(FileStorage::class, Mockery::mock(FileStorage::class));

        $this->postJson(
            "/api/projects/{$project->id}/scripts/{$script->id}/parse",
            [],
            $this->authHeader(self::OWNER_ID),
        )->assertStatus(409)->assertJsonPath('code', 'SCRIPT_ALREADY_PARSED');
    }

    public function test_parse_unknown_script_returns_not_found(): void
    {
        $project = $this->makeProject();

        $this->app->instance(FileStorage::class, Mockery::mock(FileStorage::class));

        $this->postJson(
            "/api/projects/{$project->id}/scripts/44444444-4444-4444-4444-444444444444/parse",
            [],
            $this->authHeader(self::OWNER_ID),
        )->assertStatus(404)->assertJsonPath('code', 'SCRIPT_NOT_FOUND');
    }

    public function test_viewer_cannot_parse(): void
    {
        $project = $this->makeProject();
        $script = $this->seedUploadedScript($project);

        $this->postJson(
            "/api/projects/{$project->id}/scripts/{$script->id}/parse",
            [],
            $this->authHeader(self::VIEWER_ID),
        )->assertStatus(403)->assertJsonPath('code', 'FORBIDDEN_PROJECT');
    }

    private function seedUploadedScript(Project $project): Script
    {
        return Script::create([
            'project_id' => $project->id,
            'uploaded_by' => self::OWNER_ID,
            'file_name' => 'scenario.fdx',
            'storage_bucket' => 'fdx-files',
            'storage_path' => "{$project->id}/script/original.fdx",
            'file_size_bytes' => 1234,
            'parse_status' => 'uploaded',
        ]);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
