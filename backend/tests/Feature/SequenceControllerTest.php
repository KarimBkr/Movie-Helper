<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\ProjectMember;
use App\Models\Script;
use App\Models\Sequence;
use App\Models\SequenceElement;
use Firebase\JWT\JWT;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tableau de dépouillement (L-08) : lecture des séquences + validation (L-09).
 */
class SequenceControllerTest extends TestCase
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

    private function makeSequence(Project $project, array $attributes = []): Sequence
    {
        $script = Script::firstOrCreate(
            ['project_id' => $project->id, 'file_name' => 'scenario.fdx'],
            [
                'uploaded_by' => self::OWNER_ID,
                'storage_bucket' => 'fdx-files',
                'storage_path' => "{$project->id}/script/original.fdx",
                'parse_status' => 'parsed',
                'sequence_count' => 1,
            ],
        );

        return Sequence::create(array_merge([
            'project_id' => $project->id,
            'script_id' => $script->id,
            'display_order' => 1,
            'scene_number' => '1',
            'int_ext' => 'INT',
            'day_night' => 'JOUR',
            'decor' => 'CUISINE',
            'raw_text' => "INT. CUISINE - JOUR\nMarc entre.",
            'status' => 'ia',
        ], $attributes));
    }

    // --- index ---

    public function test_index_requires_auth(): void
    {
        $this->getJson('/api/projects/x/sequences')->assertStatus(401);
    }

    public function test_member_can_list_sequences_with_element_count(): void
    {
        $project = $this->makeProject();
        $sequence = $this->makeSequence($project);
        SequenceElement::create([
            'project_id' => $project->id,
            'sequence_id' => $sequence->id,
            'category' => 'accessoire',
            'value' => 'revolver',
            'source_text' => 'Marc pose le revolver.',
            'confidence' => 'high',
            'status' => 'ia',
        ]);

        // Le viewer a accès en lecture.
        $this->getJson(
            "/api/projects/{$project->id}/sequences",
            $this->authHeader(self::VIEWER_ID),
        )->assertStatus(200)
            ->assertJsonPath('data.0.scene_number', '1')
            ->assertJsonPath('data.0.elements_count', 1)
            ->assertJsonPath('data.0.int_ext', 'INT');
    }

    public function test_index_returns_flags_as_array(): void
    {
        $project = $this->makeProject();
        // Écrit un text[] Postgres directement pour vérifier le cast en lecture.
        $sequence = $this->makeSequence($project);
        $sequence->flags = ['continuite', 'à vérifier'];
        $sequence->save();

        $this->getJson(
            "/api/projects/{$project->id}/sequences",
            $this->authHeader(self::OWNER_ID),
        )->assertStatus(200)
            ->assertJsonPath('data.0.flags', ['continuite', 'à vérifier']);
    }

    public function test_stranger_cannot_list_sequences(): void
    {
        $project = $this->makeProject();
        $this->makeSequence($project);

        $this->getJson(
            "/api/projects/{$project->id}/sequences",
            $this->authHeader(self::STRANGER_ID),
        )->assertStatus(404)->assertJsonPath('code', 'PROJECT_NOT_FOUND');
    }

    // --- show ---

    public function test_member_can_view_sequence_with_elements(): void
    {
        $project = $this->makeProject();
        $sequence = $this->makeSequence($project);
        SequenceElement::create([
            'project_id' => $project->id,
            'sequence_id' => $sequence->id,
            'category' => 'personnage',
            'value' => 'Marc',
            'source_text' => 'Marc entre.',
            'confidence' => 'high',
            'status' => 'ia',
        ]);

        $this->getJson(
            "/api/projects/{$project->id}/sequences/{$sequence->id}",
            $this->authHeader(self::OWNER_ID),
        )->assertStatus(200)
            ->assertJsonPath('data.id', $sequence->id)
            ->assertJsonPath('data.elements.0.value', 'Marc')
            ->assertJsonPath('data.elements.0.category', 'personnage');
    }

    public function test_show_unknown_sequence_returns_not_found(): void
    {
        $project = $this->makeProject();

        $this->getJson(
            "/api/projects/{$project->id}/sequences/44444444-4444-4444-4444-444444444444",
            $this->authHeader(self::OWNER_ID),
        )->assertStatus(404)->assertJsonPath('code', 'SEQUENCE_NOT_FOUND');
    }

    // --- validate ---

    public function test_owner_can_validate_sequence(): void
    {
        $project = $this->makeProject();
        $sequence = $this->makeSequence($project);

        $this->patchJson(
            "/api/sequences/{$sequence->id}/validate",
            ['status' => 'verifie'],
            $this->authHeader(self::OWNER_ID),
        )->assertStatus(200)
            ->assertJsonPath('data.status', 'verifie');

        $sequence->refresh();
        $this->assertSame('verifie', $sequence->status);
        $this->assertNotNull($sequence->validated_at);
        $this->assertSame(self::OWNER_ID, $sequence->validated_by);
    }

    public function test_viewer_cannot_validate_sequence(): void
    {
        $project = $this->makeProject();
        $sequence = $this->makeSequence($project);

        $this->patchJson(
            "/api/sequences/{$sequence->id}/validate",
            ['status' => 'verifie'],
            $this->authHeader(self::VIEWER_ID),
        )->assertStatus(403)->assertJsonPath('code', 'FORBIDDEN_PROJECT');
    }

    public function test_validate_rejects_invalid_status(): void
    {
        $project = $this->makeProject();
        $sequence = $this->makeSequence($project);

        $this->patchJson(
            "/api/sequences/{$sequence->id}/validate",
            ['status' => 'ia'],
            $this->authHeader(self::OWNER_ID),
        )->assertStatus(422)->assertJsonPath('code', 'INVALID_SEQUENCE_ELEMENT');
    }
}
