<?php

namespace Tests\Unit;

use App\DTOs\Claude\SequenceBreakdown;
use App\Exceptions\ClaudeException;
use App\Services\Claude\SequenceBreakdownTool;
use PHPUnit\Framework\TestCase;

/**
 * Tests purs (sans app/DB) du contrat IA L-04 : le schéma tool_use et le
 * parsing/validation de l'input renvoyé par Claude.
 */
class SequenceBreakdownTest extends TestCase
{
    // --- Schéma tool_use (règle 3 : strict, input_schema complet) ---

    public function test_tool_definition_exposes_name_and_full_schema(): void
    {
        $def = SequenceBreakdownTool::definition();

        $this->assertSame('extract_sequence_breakdown', $def['name']);
        $this->assertNotEmpty($def['description']);
        $this->assertIsArray($def['input_schema']);
        $this->assertNotEmpty($def['input_schema']['properties']);
    }

    public function test_input_schema_is_strict_object_with_required_top_level_keys(): void
    {
        $schema = SequenceBreakdownTool::inputSchema();

        $this->assertSame('object', $schema['type']);
        $this->assertFalse($schema['additionalProperties']);
        $this->assertEqualsCanonicalizing(
            ['sequence', 'elements', 'flags', 'notes'],
            $schema['required'],
        );
    }

    public function test_sequence_schema_exposes_direct_fields_only(): void
    {
        $sequence = SequenceBreakdownTool::inputSchema()['properties']['sequence'];

        // Champs directs de la table sequences, pas des sequence_elements.
        $this->assertEqualsCanonicalizing(
            ['scene_number', 'resume', 'decor', 'int_ext', 'jour_nuit', 'huitiemes'],
            $sequence['required'],
        );
        $this->assertFalse($sequence['additionalProperties']);
    }

    public function test_element_items_require_source_text_and_use_v1_categories(): void
    {
        $items = SequenceBreakdownTool::inputSchema()['properties']['elements']['items'];

        $this->assertContains('source_text', $items['required']);
        $this->assertSame(1, $items['properties']['source_text']['minLength']);
        $this->assertFalse($items['additionalProperties']);

        // Aucune catégorie hors V1 ni champ direct de séquence.
        $categories = $items['properties']['category']['enum'];
        $this->assertContains('personnage', $categories);
        $this->assertContains('accessoire', $categories);
        $this->assertNotContains('maquillage', $categories);
        $this->assertNotContains('decor', $categories);
        $this->assertNotContains('resume', $categories);
    }

    // --- Parsing de l'input tool_use ---

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
            'flags' => ['Numéro de scène ambigu'],
            'notes' => ['Scène courte'],
        ];
    }

    public function test_parses_valid_input_into_typed_dto(): void
    {
        $breakdown = SequenceBreakdown::fromToolInput($this->validInput());

        $this->assertSame('12', $breakdown->sceneNumber);
        $this->assertSame('Marc entre.', $breakdown->field('resume')->value);
        $this->assertSame('INT', $breakdown->field('int_ext')->value);
        $this->assertSame(2, $breakdown->field('huitiemes')->value);
        $this->assertCount(1, $breakdown->elements);
        $this->assertSame('revolver', $breakdown->elements[0]->value);
        $this->assertSame(['Numéro de scène ambigu'], $breakdown->flags);
        $this->assertSame(['Scène courte'], $breakdown->notes);
    }

    public function test_rejects_element_without_source_text(): void
    {
        $input = $this->validInput();
        $input['elements'][] = ['category' => 'accessoire', 'value' => 'couteau', 'source_text' => '', 'confidence' => 'low', 'note' => ''];
        $input['elements'][] = ['category' => 'accessoire', 'value' => 'corde', 'source_text' => '   ', 'confidence' => 'low', 'note' => ''];

        $breakdown = SequenceBreakdown::fromToolInput($input);

        // Règle 7 : seuls les éléments avec source_text non vide sont retenus.
        $this->assertCount(1, $breakdown->elements);
        $this->assertSame('revolver', $breakdown->elements[0]->value);
        $this->assertCount(2, $breakdown->rejectedElements);
    }

    public function test_rejects_element_without_value(): void
    {
        $input = $this->validInput();
        $input['elements'][] = ['category' => 'accessoire', 'value' => '', 'source_text' => 'du texte', 'confidence' => 'low', 'note' => ''];

        $breakdown = SequenceBreakdown::fromToolInput($input);

        $this->assertCount(1, $breakdown->elements);
        $this->assertCount(1, $breakdown->rejectedElements);
    }

    public function test_empty_elements_and_lists_are_handled(): void
    {
        $input = $this->validInput();
        $input['elements'] = [];
        $input['flags'] = [];
        $input['notes'] = [];

        $breakdown = SequenceBreakdown::fromToolInput($input);

        $this->assertSame([], $breakdown->elements);
        $this->assertSame([], $breakdown->flags);
        $this->assertSame([], $breakdown->notes);
    }

    public function test_throws_when_sequence_block_missing(): void
    {
        $this->expectException(ClaudeException::class);

        SequenceBreakdown::fromToolInput(['elements' => [], 'flags' => [], 'notes' => []]);
    }

    public function test_null_scene_number_is_preserved(): void
    {
        $input = $this->validInput();
        $input['sequence']['scene_number'] = null;

        $breakdown = SequenceBreakdown::fromToolInput($input);

        $this->assertNull($breakdown->sceneNumber);
    }
}
