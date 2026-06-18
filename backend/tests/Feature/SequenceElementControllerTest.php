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
 * Correction humaine des éléments de dépouillement (L-08/L-09) :
 * création manuelle, édition (ia → corrige), suppression soft, règle 7.
 */
class SequenceElementControllerTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'test-jwt-secret-longue-au-moins-32-chars';

    private const OWNER_ID = '11111111-1111-1111-1111-111111111111';

    private const VIEWER_ID = '22222222-2222-2222-2222-222222222222';

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

    private function makeSequence(Project $project): Sequence
    {
        $script = Script::create([
            'project_id' => $project->id,
            'uploaded_by' => self::OWNER_ID,
            'file_name' => 'scenario.fdx',
            'storage_bucket' => 'fdx-files',
            'storage_path' => "{$project->id}/script/original.fdx",
            'parse_status' => 'parsed',
            'sequence_count' => 1,
        ]);

        return Sequence::create([
            'project_id' => $project->id,
            'script_id' => $script->id,
            'display_order' => 1,
            'int_ext' => 'INT',
            'day_night' => 'JOUR',
            'raw_text' => "INT. CUISINE - JOUR\nMarc entre.",
            'status' => 'ia',
        ]);
    }

    private function aiElement(Project $project, Sequence $sequence): SequenceElement
    {
        return SequenceElement::create([
            'project_id' => $project->id,
            'sequence_id' => $sequence->id,
            'category' => 'accessoire',
            'value' => 'revolver',
            'source_text' => 'Marc pose le revolver.',
            'confidence' => 'high',
            'status' => 'ia',
        ]);
    }

    // --- store ---

    public function test_owner_can_create_manual_element(): void
    {
        $project = $this->makeProject();
        $sequence = $this->makeSequence($project);

        $this->postJson(
            "/api/sequences/{$sequence->id}/elements",
            ['category' => 'costume', 'value' => 'imperméable beige'],
            $this->authHeader(self::OWNER_ID),
        )->assertStatus(201)
            ->assertJsonPath('data.category', 'costume')
            ->assertJsonPath('data.value', 'imperméable beige')
            // Un élément créé à la main est en statut 'manuel'.
            ->assertJsonPath('data.status', 'manuel');

        $this->assertDatabaseHas('sequence_elements', [
            'sequence_id' => $sequence->id,
            'value' => 'imperméable beige',
            'status' => 'manuel',
            'created_by' => self::OWNER_ID,
        ]);
    }

    public function test_viewer_cannot_create_element(): void
    {
        $project = $this->makeProject();
        $sequence = $this->makeSequence($project);

        $this->postJson(
            "/api/sequences/{$sequence->id}/elements",
            ['category' => 'costume', 'value' => 'chapeau'],
            $this->authHeader(self::VIEWER_ID),
        )->assertStatus(403)->assertJsonPath('code', 'FORBIDDEN_PROJECT');

        $this->assertDatabaseMissing('sequence_elements', ['value' => 'chapeau']);
    }

    public function test_create_rejects_invalid_category(): void
    {
        $project = $this->makeProject();
        $sequence = $this->makeSequence($project);

        $this->postJson(
            "/api/sequences/{$sequence->id}/elements",
            ['category' => 'maquillage', 'value' => 'fond de teint'],
            $this->authHeader(self::OWNER_ID),
        )->assertStatus(422)->assertJsonPath('code', 'INVALID_SEQUENCE_ELEMENT');
    }

    public function test_create_rejects_empty_value(): void
    {
        $project = $this->makeProject();
        $sequence = $this->makeSequence($project);

        $this->postJson(
            "/api/sequences/{$sequence->id}/elements",
            ['category' => 'accessoire', 'value' => '   '],
            $this->authHeader(self::OWNER_ID),
        )->assertStatus(422)->assertJsonPath('code', 'INVALID_SEQUENCE_ELEMENT');
    }

    public function test_create_ia_element_without_source_text_is_rejected(): void
    {
        $project = $this->makeProject();
        $sequence = $this->makeSequence($project);

        // Règle 7 : un élément 'ia' sans source_text est refusé.
        $this->postJson(
            "/api/sequences/{$sequence->id}/elements",
            ['category' => 'accessoire', 'value' => 'couteau', 'status' => 'ia'],
            $this->authHeader(self::OWNER_ID),
        )->assertStatus(422)->assertJsonPath('code', 'MISSING_SOURCE_TEXT');
    }

    public function test_create_on_unknown_sequence_returns_not_found(): void
    {
        $project = $this->makeProject();

        $this->postJson(
            '/api/sequences/44444444-4444-4444-4444-444444444444/elements',
            ['category' => 'accessoire', 'value' => 'couteau'],
            $this->authHeader(self::OWNER_ID),
        )->assertStatus(404)->assertJsonPath('code', 'SEQUENCE_NOT_FOUND');
    }

    // --- update ---

    public function test_editing_ia_element_moves_it_to_corrige(): void
    {
        $project = $this->makeProject();
        $sequence = $this->makeSequence($project);
        $element = $this->aiElement($project, $sequence);

        $this->patchJson(
            "/api/elements/{$element->id}",
            ['value' => 'pistolet'],
            $this->authHeader(self::OWNER_ID),
        )->assertStatus(200)
            ->assertJsonPath('data.value', 'pistolet')
            // Éditer une proposition IA = décision humaine → 'corrige'.
            ->assertJsonPath('data.status', 'corrige');
    }

    public function test_explicit_status_takes_precedence_on_update(): void
    {
        $project = $this->makeProject();
        $sequence = $this->makeSequence($project);
        $element = $this->aiElement($project, $sequence);

        // Valider tel quel sans modifier la valeur.
        $this->patchJson(
            "/api/elements/{$element->id}",
            ['status' => 'verifie'],
            $this->authHeader(self::OWNER_ID),
        )->assertStatus(200)->assertJsonPath('data.status', 'verifie');
    }

    public function test_update_cannot_revert_to_ia_without_source_text(): void
    {
        $project = $this->makeProject();
        $sequence = $this->makeSequence($project);
        // Élément manuel sans source_text.
        $element = SequenceElement::create([
            'project_id' => $project->id,
            'sequence_id' => $sequence->id,
            'category' => 'note',
            'value' => 'à confirmer',
            'confidence' => 'low',
            'status' => 'manuel',
        ]);

        $this->patchJson(
            "/api/elements/{$element->id}",
            ['status' => 'ia'],
            $this->authHeader(self::OWNER_ID),
        )->assertStatus(422)->assertJsonPath('code', 'MISSING_SOURCE_TEXT');
    }

    public function test_viewer_cannot_update_element(): void
    {
        $project = $this->makeProject();
        $sequence = $this->makeSequence($project);
        $element = $this->aiElement($project, $sequence);

        $this->patchJson(
            "/api/elements/{$element->id}",
            ['value' => 'pistolet'],
            $this->authHeader(self::VIEWER_ID),
        )->assertStatus(403)->assertJsonPath('code', 'FORBIDDEN_PROJECT');
    }

    public function test_update_unknown_element_returns_not_found(): void
    {
        $this->makeProject();

        $this->patchJson(
            '/api/elements/44444444-4444-4444-4444-444444444444',
            ['value' => 'x'],
            $this->authHeader(self::OWNER_ID),
        )->assertStatus(404)->assertJsonPath('code', 'ELEMENT_NOT_FOUND');
    }

    // --- destroy ---

    public function test_owner_can_soft_delete_element(): void
    {
        $project = $this->makeProject();
        $sequence = $this->makeSequence($project);
        $element = $this->aiElement($project, $sequence);

        $this->deleteJson(
            "/api/elements/{$element->id}",
            [],
            $this->authHeader(self::OWNER_ID),
        )->assertStatus(204);

        // Soft delete : la ligne reste mais deleted_at est renseigné.
        $this->assertSoftDeleted('sequence_elements', ['id' => $element->id]);
    }

    public function test_viewer_cannot_delete_element(): void
    {
        $project = $this->makeProject();
        $sequence = $this->makeSequence($project);
        $element = $this->aiElement($project, $sequence);

        $this->deleteJson(
            "/api/elements/{$element->id}",
            [],
            $this->authHeader(self::VIEWER_ID),
        )->assertStatus(403)->assertJsonPath('code', 'FORBIDDEN_PROJECT');

        $this->assertDatabaseHas('sequence_elements', ['id' => $element->id, 'deleted_at' => null]);
    }
}
