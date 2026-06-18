<?php

namespace App\Http\Controllers;

use App\Actions\UpsertSequenceElementAction;
use App\Exceptions\SequenceException;
use App\Models\Sequence;
use App\Models\SequenceElement;
use App\Services\ProjectAccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Correction humaine des éléments de dépouillement (L-08/L-09).
 *
 * Toute écriture est réservée à l'owner. La logique métier (statuts de
 * correction, règle 7) vit dans UpsertSequenceElementAction. Soft delete sur
 * suppression (convention CLAUDE.md).
 */
class SequenceElementController extends Controller
{
    public function __construct(
        private readonly ProjectAccessService $access,
        private readonly UpsertSequenceElementAction $upsert,
    ) {}

    /**
     * POST /api/sequences/{sequence}/elements
     */
    public function store(Request $request, string $sequenceId): JsonResponse
    {
        $user = $request->attributes->get('auth_user');

        $sequence = Sequence::where('id', $sequenceId)->first();

        if ($sequence === null) {
            throw SequenceException::sequenceNotFound();
        }

        $this->access->requireOwner($sequence->project_id, $user->id);

        $element = $this->upsert->create($sequence, $request->all(), $user->id);

        return response()->json(['data' => $this->formatElement($element)], 201);
    }

    /**
     * PATCH /api/elements/{element}
     */
    public function update(Request $request, string $elementId): JsonResponse
    {
        $user = $request->attributes->get('auth_user');

        $element = $this->findElement($elementId);

        $this->access->requireOwner($element->project_id, $user->id);

        $element = $this->upsert->update($element, $request->all(), $user->id);

        return response()->json(['data' => $this->formatElement($element)]);
    }

    /**
     * DELETE /api/elements/{element}
     * Soft delete (deleted_at).
     */
    public function destroy(Request $request, string $elementId): JsonResponse
    {
        $user = $request->attributes->get('auth_user');

        $element = $this->findElement($elementId);

        $this->access->requireOwner($element->project_id, $user->id);

        $element->delete();

        return response()->json(null, 204);
    }

    private function findElement(string $elementId): SequenceElement
    {
        $element = SequenceElement::where('id', $elementId)->first();

        if ($element === null) {
            throw SequenceException::elementNotFound();
        }

        return $element;
    }

    /**
     * @return array<string,mixed>
     */
    private function formatElement(SequenceElement $element): array
    {
        return [
            'id' => $element->id,
            'sequence_id' => $element->sequence_id,
            'category' => $element->category,
            'value' => $element->value,
            'source_text' => $element->source_text,
            'confidence' => $element->confidence,
            'status' => $element->status,
            'note' => $element->note,
            'created_at' => $element->created_at,
            'updated_at' => $element->updated_at,
        ];
    }
}
