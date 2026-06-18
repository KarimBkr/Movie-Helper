<?php

namespace App\Http\Controllers;

use App\Exceptions\SequenceException;
use App\Models\Sequence;
use App\Models\SequenceElement;
use App\Services\ProjectAccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Tableau de dépouillement : lecture des séquences et validation humaine (L-08/L-09).
 *
 * Lecture (index/show) ouverte à tout membre ; validate = décision humaine,
 * réservée à l'owner. L'accès projet passe toujours par ProjectAccessService.
 */
class SequenceController extends Controller
{
    public function __construct(
        private readonly ProjectAccessService $access,
    ) {}

    /**
     * GET /api/projects/{project}/sequences
     */
    public function index(Request $request, string $projectId): JsonResponse
    {
        $user = $request->attributes->get('auth_user');
        $this->access->requireMember($projectId, $user->id);

        $sequences = Sequence::where('project_id', $projectId)
            ->withCount('elements')
            ->orderBy('display_order')
            ->get();

        return response()->json([
            'data' => $sequences->map(fn (Sequence $s): array => $this->formatSequence($s))->all(),
        ]);
    }

    /**
     * GET /api/projects/{project}/sequences/{sequence}
     * Détail d'une séquence avec ses éléments (hors soft-deleted).
     */
    public function show(Request $request, string $projectId, string $sequenceId): JsonResponse
    {
        $user = $request->attributes->get('auth_user');
        $this->access->requireMember($projectId, $user->id);

        $sequence = $this->findSequence($projectId, $sequenceId);
        $sequence->load(['elements' => fn ($q) => $q->orderBy('category')->orderBy('created_at')]);

        return response()->json([
            'data' => $this->formatSequence($sequence, withElements: true),
        ]);
    }

    /**
     * PATCH /api/sequences/{sequence}/validate
     * Valide (ou corrige) une séquence — décision humaine, owner uniquement (L-09).
     */
    public function validateSequence(Request $request, string $sequenceId): JsonResponse
    {
        $user = $request->attributes->get('auth_user');

        $sequence = Sequence::where('id', $sequenceId)->first();

        if ($sequence === null) {
            throw SequenceException::sequenceNotFound();
        }

        $this->access->requireOwner($sequence->project_id, $user->id);

        $status = $request->input('status', 'verifie');

        if (! in_array($status, ['verifie', 'corrige', 'manuel'], true)) {
            throw SequenceException::invalidElement(
                'Statut de séquence invalide.',
                ['status' => ['Statut non reconnu. Attendu : verifie, corrige ou manuel.']],
            );
        }

        $sequence->update([
            'status' => $status,
            'validated_at' => now(),
            'validated_by' => $user->id,
        ]);

        return response()->json(['data' => $this->formatSequence($sequence)]);
    }

    private function findSequence(string $projectId, string $sequenceId): Sequence
    {
        $sequence = Sequence::where('id', $sequenceId)
            ->where('project_id', $projectId)
            ->first();

        if ($sequence === null) {
            throw SequenceException::sequenceNotFound();
        }

        return $sequence;
    }

    /**
     * @return array<string,mixed>
     */
    private function formatSequence(Sequence $sequence, bool $withElements = false): array
    {
        $data = [
            'id' => $sequence->id,
            'project_id' => $sequence->project_id,
            'script_id' => $sequence->script_id,
            'display_order' => $sequence->display_order,
            'scene_number' => $sequence->scene_number,
            'scene_heading' => $sequence->scene_heading,
            'decor' => $sequence->decor,
            'sub_decor' => $sequence->sub_decor,
            'int_ext' => $sequence->int_ext,
            'day_night' => $sequence->day_night,
            'resume' => $sequence->resume,
            'huitiemes' => $sequence->huitiemes,
            'status' => $sequence->status,
            'flags' => $sequence->flags,
            'validated_at' => $sequence->validated_at,
            'created_at' => $sequence->created_at,
        ];

        if ($sequence->elements_count !== null) {
            $data['elements_count'] = $sequence->elements_count;
        }

        if ($withElements) {
            $data['elements'] = $sequence->elements
                ->map(fn ($e): array => $this->formatElement($e))
                ->all();
        }

        return $data;
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
