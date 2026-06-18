<?php

namespace App\Actions;

use App\Contracts\FileStorage;
use App\DTOs\ParsedSequence;
use App\Exceptions\ScriptException;
use App\Models\Script;
use App\Models\Sequence;
use App\Services\FdxParserService;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Parsing d'un scénario déjà uploadé (L-03).
 *
 * Télécharge le FDX depuis Storage, le passe au parser déterministe, puis
 * persiste les séquences et met à jour le script. Multi-tables → transaction.
 * Le parser n'appelle jamais l'IA (règle 6 de CLAUDE.md).
 */
class ParseScriptAction
{
    public function __construct(
        private readonly FileStorage $storage,
        private readonly FdxParserService $parser,
    ) {}

    public function execute(Script $script): Script
    {
        if ($script->parse_status === 'parsed') {
            throw ScriptException::alreadyParsed();
        }

        $xml = $this->storage->download($script->storage_bucket, $script->storage_path);

        try {
            $result = $this->parser->parse($xml);
        } catch (RuntimeException $e) {
            // Parsing impossible → on marque l'échec et on remonte une erreur métier.
            $script->update([
                'parse_status' => 'parse_failed',
                'parse_error' => $e->getMessage(),
            ]);

            throw ScriptException::invalidFdx($e->getMessage());
        }

        return DB::transaction(function () use ($script, $result): Script {
            // Re-parsing : on repart d'une ardoise propre pour ce script.
            Sequence::where('script_id', $script->id)->delete();

            foreach ($result->sequences as $parsed) {
                $this->persistSequence($script, $parsed);
            }

            $script->update([
                'parse_status' => 'parsed',
                'parse_error' => null,
                'sequence_count' => $result->sequenceCount(),
                'parsed_at' => now(),
            ]);

            return $script->refresh();
        });
    }

    private function persistSequence(Script $script, ParsedSequence $parsed): void
    {
        Sequence::create([
            'project_id' => $script->project_id,
            'script_id' => $script->id,
            'display_order' => $parsed->displayOrder,
            'scene_number' => $parsed->sceneNumber,
            'scene_heading' => $parsed->sceneHeading,
            'decor' => $parsed->decor,
            'sub_decor' => $parsed->subDecor,
            'int_ext' => $parsed->intExt,
            'day_night' => $parsed->dayNight,
            'raw_text' => $parsed->rawText,
            'parse_status' => $parsed->parseStatus,
            'status' => 'ia',
        ]);
    }
}
