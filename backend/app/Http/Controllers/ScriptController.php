<?php

namespace App\Http\Controllers;

use App\Actions\ParseScriptAction;
use App\Actions\UploadScriptAction;
use App\Exceptions\ScriptException;
use App\Http\Requests\UploadScriptRequest;
use App\Models\Script;
use App\Services\ProjectAccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ScriptController extends Controller
{
    public function __construct(
        private readonly ProjectAccessService $access,
        private readonly UploadScriptAction $uploadScript,
        private readonly ParseScriptAction $parseScript,
    ) {}

    /**
     * POST /api/projects/{project}/scripts/upload (L-01)
     * Écriture → réservé à l'owner.
     */
    public function upload(UploadScriptRequest $request, string $projectId): JsonResponse
    {
        $user = $request->attributes->get('auth_user');
        $this->access->requireOwner($projectId, $user->id);

        $script = $this->uploadScript->execute(
            projectId: $projectId,
            userId: $user->id,
            file: $request->file('file'),
        );

        return response()->json(['data' => $this->formatScript($script)], 201);
    }

    /**
     * POST /api/projects/{project}/scripts/{script}/parse (L-03)
     * Écriture → réservé à l'owner.
     */
    public function parse(Request $request, string $projectId, string $scriptId): JsonResponse
    {
        $user = $request->attributes->get('auth_user');
        $this->access->requireOwner($projectId, $user->id);

        $script = $this->findScript($projectId, $scriptId);

        $script = $this->parseScript->execute($script);

        return response()->json(['data' => $this->formatScript($script)]);
    }

    private function findScript(string $projectId, string $scriptId): Script
    {
        $script = Script::where('id', $scriptId)
            ->where('project_id', $projectId)
            ->first();

        if ($script === null) {
            throw ScriptException::notFound();
        }

        return $script;
    }

    private function formatScript(Script $script): array
    {
        return [
            'id' => $script->id,
            'project_id' => $script->project_id,
            'file_name' => $script->file_name,
            'parse_status' => $script->parse_status,
            'parse_error' => $script->parse_error,
            'sequence_count' => $script->sequence_count,
            'parsed_at' => $script->parsed_at,
            'created_at' => $script->created_at,
        ];
    }
}
