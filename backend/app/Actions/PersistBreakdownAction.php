<?php

namespace App\Actions;

use App\DTOs\Claude\SequenceBreakdown;
use App\Models\Sequence;
use App\Models\SequenceElement;
use Illuminate\Support\Facades\DB;

/**
 * Persiste le dépouillement renvoyé par Claude pour UNE séquence (L-05/L-07).
 *
 * Frontière d'écriture entre la réponse IA et la base. Garanties :
 *   - un seul appel transactionnel (multi-tables) ;
 *   - on ne persiste que les éléments avec source_text (déjà filtrés par le DTO,
 *     règle 7 ; aussi imposé par la contrainte DB sequence_elements_ai_source_required) ;
 *   - on ne touche JAMAIS le travail humain : seuls les éléments 'ia' sont
 *     remplacés, les champs IA ne sont écrits que si la séquence est encore 'ia' ;
 *   - le parser reste autorité sur int_ext/decor/day_night (règle 6) : l'IA ne
 *     renseigne ici que resume et huitiemes.
 */
class PersistBreakdownAction
{
    public function execute(Sequence $sequence, SequenceBreakdown $breakdown): void
    {
        DB::transaction(function () use ($sequence, $breakdown): void {
            $this->replaceAiElements($sequence, $breakdown);
            $this->fillAiSequenceFields($sequence, $breakdown);
        });
    }

    /**
     * Remplace les éléments d'origine IA par ceux du nouveau dépouillement.
     * Les éléments vérifiés/corrigés/manuels par un humain sont conservés.
     */
    private function replaceAiElements(Sequence $sequence, SequenceBreakdown $breakdown): void
    {
        SequenceElement::where('sequence_id', $sequence->id)
            ->where('status', 'ia')
            ->delete();

        foreach ($breakdown->elements as $element) {
            SequenceElement::create([
                'project_id' => $sequence->project_id,
                'sequence_id' => $sequence->id,
                'category' => $element->category,
                'value' => $element->value,
                'source_text' => $element->sourceText,
                'confidence' => $this->normalizeConfidence($element->confidence),
                'status' => 'ia',
                'note' => $element->note !== '' ? $element->note : null,
            ]);
        }
    }

    /**
     * Renseigne resume et huitiemes (champs laissés à l'IA par le parser).
     * Aucune écriture si la séquence a déjà été reprise par un humain.
     */
    private function fillAiSequenceFields(Sequence $sequence, SequenceBreakdown $breakdown): void
    {
        if ($sequence->status !== 'ia') {
            return;
        }

        $update = [];

        if ($resume = $breakdown->field('resume')) {
            $value = is_string($resume->value) ? trim($resume->value) : '';
            if ($value !== '') {
                $update['resume'] = $value;
            }
        }

        if ($huitiemes = $breakdown->field('huitiemes')) {
            if (is_numeric($huitiemes->value)) {
                $update['huitiemes'] = (float) $huitiemes->value;
            }
        }

        if ($update !== []) {
            $sequence->update($update);
        }
    }

    private function normalizeConfidence(string $confidence): string
    {
        return in_array($confidence, ['high', 'medium', 'low'], true) ? $confidence : 'low';
    }
}
