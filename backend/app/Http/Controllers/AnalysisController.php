<?php

namespace App\Http\Controllers;

use App\Actions\RetryFailedAnalysisAction;
use App\Actions\StartAnalysisAction;
use App\Exceptions\AnalysisException;
use App\Exceptions\ScriptException;
use App\Models\AnalysisJob;
use App\Models\Script;
use App\Services\ProjectAccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Orchestration de l'analyse IA d'un script (L-06).
 *
 * start / retry = écritures réservées à l'owner ; show = lecture ouverte à
 * tout membre (un viewer peut suivre la progression).
 */
class AnalysisController extends Controller
{
    public function __construct(
        private readonly ProjectAccessService $access,
        private readonly StartAnalysisAction $startAnalysis,
        private readonly RetryFailedAnalysisAction $retryFailed,
    ) {}

    /**
     * POST /api/projects/{project}/analysis/start
     */
    public function start(Request $request, string $projectId): JsonResponse
    {
        $user = $request->attributes->get('auth_user');
        $this->access->requireOwner($projectId, $user->id);

        $script = $this->activeScript($projectId);

        $job = $this->startAnalysis->execute($script, $user->id);

        return response()->json(['data' => $this->formatJob($job)], 201);
    }

    /**
     * GET /api/projects/{project}/analysis/{jobId}
     */
    public function show(Request $request, string $projectId, string $jobId): JsonResponse
    {
        $user = $request->attributes->get('auth_user');
        $this->access->requireMember($projectId, $user->id);

        $job = $this->findJob($projectId, $jobId);

        return response()->json(['data' => $this->formatJob($job)]);
    }

    /**
     * POST /api/projects/{project}/analysis/{jobId}/retry-failed
     */
    public function retryFailed(Request $request, string $projectId, string $jobId): JsonResponse
    {
        $user = $request->attributes->get('auth_user');
        $this->access->requireOwner($projectId, $user->id);

        $job = $this->findJob($projectId, $jobId);

        $job = $this->retryFailed->execute($job);

        return response()->json(['data' => $this->formatJob($job)]);
    }

    /**
     * Le script actif du projet (un seul script V1). 404 si aucun.
     */
    private function activeScript(string $projectId): Script
    {
        $script = Script::where('project_id', $projectId)
            ->where('is_active', true)
            ->orderByDesc('created_at')
            ->first();

        if ($script === null) {
            throw ScriptException::notFound();
        }

        return $script;
    }

    private function findJob(string $projectId, string $jobId): AnalysisJob
    {
        $job = AnalysisJob::where('id', $jobId)
            ->where('project_id', $projectId)
            ->first();

        if ($job === null) {
            throw AnalysisException::notFound();
        }

        return $job;
    }

    private function formatJob(AnalysisJob $job): array
    {
        return [
            'id' => $job->id,
            'project_id' => $job->project_id,
            'script_id' => $job->script_id,
            'status' => $job->status,
            'total_sequences' => $job->total_sequences,
            'processed_sequences' => $job->processed_sequences,
            'failed_sequences' => $job->failed_sequences,
            'error_message' => $job->error_message,
            'started_at' => $job->started_at,
            'finished_at' => $job->finished_at,
            'created_at' => $job->created_at,
        ];
    }
}
