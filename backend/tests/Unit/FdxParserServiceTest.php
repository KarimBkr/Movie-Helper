<?php

namespace Tests\Unit;

use App\DTOs\FdxParseResult;
use App\Services\FdxParserService;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class FdxParserServiceTest extends TestCase
{
    private FdxParserService $parser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->parser = new FdxParserService;
    }

    private function fdx(string $body): string
    {
        return <<<XML
        <?xml version="1.0" encoding="UTF-8" standalone="no"?>
        <FinalDraft DocumentType="Script" Template="No Template" Version="5">
          <Content>
        $body
          </Content>
        </FinalDraft>
        XML;
    }

    public function test_parses_a_simple_int_jour_sequence(): void
    {
        $result = $this->parser->parse($this->fdx(<<<'XML'
            <Paragraph Number="1" Type="Scene Heading"><Text>INT. APPARTEMENT MARC - JOUR</Text></Paragraph>
            <Paragraph Type="Action"><Text>Marc pose le revolver sur la table.</Text></Paragraph>
        XML));

        $this->assertInstanceOf(FdxParseResult::class, $result);
        $this->assertSame(1, $result->sequenceCount());

        $seq = $result->sequences[0];
        $this->assertSame(1, $seq->displayOrder);
        $this->assertSame('1', $seq->sceneNumber);
        $this->assertSame('INT. APPARTEMENT MARC - JOUR', $seq->sceneHeading);
        $this->assertSame('INT', $seq->intExt);
        $this->assertSame('APPARTEMENT MARC', $seq->decor);
        $this->assertSame('JOUR', $seq->dayNight);
        $this->assertSame('parsed', $seq->parseStatus);
        $this->assertStringContainsString('Marc pose le revolver', $seq->rawText);
        $this->assertStringStartsWith('INT. APPARTEMENT MARC', $seq->rawText);
    }

    public function test_each_scene_heading_opens_a_new_sequence(): void
    {
        $result = $this->parser->parse($this->fdx(<<<'XML'
            <Paragraph Number="1" Type="Scene Heading"><Text>INT. CUISINE - JOUR</Text></Paragraph>
            <Paragraph Type="Action"><Text>Premiere scene.</Text></Paragraph>
            <Paragraph Number="2" Type="Scene Heading"><Text>EXT. RUE - NUIT</Text></Paragraph>
            <Paragraph Type="Action"><Text>Deuxieme scene.</Text></Paragraph>
        XML));

        $this->assertSame(2, $result->sequenceCount());
        $this->assertSame('INT', $result->sequences[0]->intExt);
        $this->assertSame('JOUR', $result->sequences[0]->dayNight);
        $this->assertSame('EXT', $result->sequences[1]->intExt);
        $this->assertSame('NUIT', $result->sequences[1]->dayNight);
        $this->assertSame(2, $result->sequences[1]->displayOrder);
        $this->assertStringContainsString('Deuxieme scene', $result->sequences[1]->rawText);
        $this->assertStringNotContainsString('Premiere scene', $result->sequences[1]->rawText);
    }

    public function test_concatenates_multiple_text_runs_without_extra_space(): void
    {
        $result = $this->parser->parse($this->fdx(<<<'XML'
            <Paragraph Number="1" Type="Scene Heading"><Text>INT. BUREAU - JOUR</Text></Paragraph>
            <Paragraph Type="Dialogue"><Text>Tu n'aurais jamais du </Text><Text Style="Italic">le</Text><Text> garder.</Text></Paragraph>
        XML));

        $this->assertStringContainsString("Tu n'aurais jamais du le garder.", $result->sequences[0]->rawText);
    }

    public function test_handles_multiple_characters_dialogue(): void
    {
        $result = $this->parser->parse($this->fdx(<<<'XML'
            <Paragraph Number="1" Type="Scene Heading"><Text>INT. SALON - SOIR</Text></Paragraph>
            <Paragraph Type="Character"><Text>LEA</Text></Paragraph>
            <Paragraph Type="Parenthetical"><Text>(essoufflee)</Text></Paragraph>
            <Paragraph Type="Dialogue"><Text>Tu es la.</Text></Paragraph>
            <Paragraph Type="Character"><Text>MARC</Text></Paragraph>
            <Paragraph Type="Dialogue"><Text>Trop tard.</Text></Paragraph>
        XML));

        $this->assertSame(1, $result->sequenceCount());
        $raw = $result->sequences[0]->rawText;
        $this->assertStringContainsString('LEA', $raw);
        $this->assertStringContainsString('(essoufflee)', $raw);
        $this->assertStringContainsString('MARC', $raw);
        $this->assertStringContainsString('Trop tard.', $raw);
        $this->assertSame('SOIR', $result->sequences[0]->dayNight);
    }

    public function test_incomplete_heading_without_moment_is_needs_review(): void
    {
        $result = $this->parser->parse($this->fdx(<<<'XML'
            <Paragraph Number="1" Type="Scene Heading"><Text>INT. APPARTEMENT MARC</Text></Paragraph>
            <Paragraph Type="Action"><Text>Action.</Text></Paragraph>
        XML));

        $seq = $result->sequences[0];
        $this->assertSame('INT', $seq->intExt);
        $this->assertSame('APPARTEMENT MARC', $seq->decor);
        $this->assertSame('UNKNOWN', $seq->dayNight);
        $this->assertSame('needs_review', $seq->parseStatus);
    }

    public function test_heading_without_int_ext_prefix_is_needs_review(): void
    {
        $result = $this->parser->parse($this->fdx(<<<'XML'
            <Paragraph Number="1" Type="Scene Heading"><Text>LE PONT - NUIT</Text></Paragraph>
            <Paragraph Type="Action"><Text>Action.</Text></Paragraph>
        XML));

        $seq = $result->sequences[0];
        $this->assertSame('UNKNOWN', $seq->intExt);
        $this->assertSame('NUIT', $seq->dayNight);
        $this->assertSame('needs_review', $seq->parseStatus);
    }

    public function test_mixed_int_ext_heading(): void
    {
        $result = $this->parser->parse($this->fdx(<<<'XML'
            <Paragraph Number="1" Type="Scene Heading"><Text>INT./EXT. VOITURE - JOUR</Text></Paragraph>
            <Paragraph Type="Action"><Text>Action.</Text></Paragraph>
        XML));

        $seq = $result->sequences[0];
        $this->assertSame('INT/EXT', $seq->intExt);
        $this->assertSame('VOITURE', $seq->decor);
        $this->assertSame('JOUR', $seq->dayNight);
        $this->assertSame('parsed', $seq->parseStatus);
    }

    public function test_text_before_first_heading_becomes_implicit_needs_review_sequence(): void
    {
        $result = $this->parser->parse($this->fdx(<<<'XML'
            <Paragraph Type="Action"><Text>Texte orphelin avant tout heading.</Text></Paragraph>
            <Paragraph Number="1" Type="Scene Heading"><Text>INT. SALLE - JOUR</Text></Paragraph>
            <Paragraph Type="Action"><Text>Vraie scene.</Text></Paragraph>
        XML));

        $this->assertSame(2, $result->sequenceCount());

        $implicit = $result->sequences[0];
        $this->assertNull($implicit->sceneHeading);
        $this->assertSame('UNKNOWN', $implicit->intExt);
        $this->assertSame('needs_review', $implicit->parseStatus);
        $this->assertStringContainsString('Texte orphelin', $implicit->rawText);

        $this->assertSame('parsed', $result->sequences[1]->parseStatus);
        $this->assertTrue($result->hasNeedsReview());
    }

    public function test_empty_paragraphs_do_not_break_numbering(): void
    {
        $result = $this->parser->parse($this->fdx(<<<'XML'
            <Paragraph Number="1" Type="Scene Heading"><Text>INT. SALLE - JOUR</Text></Paragraph>
            <Paragraph Type="Action"></Paragraph>
            <Paragraph Type="Action"><Text>Apres une ligne vide.</Text></Paragraph>
        XML));

        $this->assertSame(1, $result->sequenceCount());
        $this->assertStringContainsString('Apres une ligne vide', $result->sequences[0]->rawText);
    }

    public function test_decor_with_sub_location(): void
    {
        $result = $this->parser->parse($this->fdx(<<<'XML'
            <Paragraph Number="1" Type="Scene Heading"><Text>INT. APPARTEMENT, CHAMBRE - NUIT</Text></Paragraph>
            <Paragraph Type="Action"><Text>Action.</Text></Paragraph>
        XML));

        $seq = $result->sequences[0];
        $this->assertSame('APPARTEMENT', $seq->decor);
        $this->assertSame('CHAMBRE', $seq->subDecor);
        $this->assertSame('NUIT', $seq->dayNight);
    }

    public function test_throws_on_empty_content(): void
    {
        $this->expectException(RuntimeException::class);
        $this->parser->parse('');
    }

    public function test_throws_on_invalid_xml(): void
    {
        $this->expectException(RuntimeException::class);
        $this->parser->parse('<FinalDraft><Content><Paragraph>pas ferme');
    }

    public function test_throws_when_root_is_not_finaldraft(): void
    {
        $this->expectException(RuntimeException::class);
        $this->parser->parse('<?xml version="1.0"?><Document><Content/></Document>');
    }

    public function test_no_scene_heading_at_all_yields_single_implicit_sequence(): void
    {
        $result = $this->parser->parse($this->fdx(<<<'XML'
            <Paragraph Type="Action"><Text>Juste de l'action, aucun heading.</Text></Paragraph>
            <Paragraph Type="Action"><Text>Encore de l'action.</Text></Paragraph>
        XML));

        $this->assertSame(1, $result->sequenceCount());
        $this->assertSame('needs_review', $result->sequences[0]->parseStatus);
        $this->assertStringContainsString('Juste de l\'action', $result->sequences[0]->rawText);
        $this->assertStringContainsString('Encore de l\'action', $result->sequences[0]->rawText);
    }
}
