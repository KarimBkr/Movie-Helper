<?php

namespace App\Actions;

use App\Jobs\AnalyzeSequenceJob;
use App\Models\AnalysisJob;
use App\Models\AnalysisJobItem;
use Illuminate\Support\Facades\DB;

/**
 * Relance les séquences en échec d'une analyse (L-06, POST /retry-failed).
 *
 * Remet les items 'failed' à 'pending', repasse le job en 'running', puis
 * re-dispatch un AnalyzeSequenceJob par item relancé (après commit). Sans item
 * en échec, ne fait rien et renvoie le job inchangé.
 */
class RetryFailedAnalysisAction
{
    public function execute(AnalysisJob $job): AnalysisJob
    {
        $failedItemIds = AnalysisJobItem::where('analysis_job_id', $job->id)
            ->where('status', 'failed')
            ->pluck('id');

        if ($failedItemIds->isEmpty()) {
            return $job;
        }

        DB::transaction(function () use ($job, $failedItemIds): void {
            AnalysisJobItem::whereIn('id', $failedItemIds)->update([
                'status' => 'pending',
                'error_message' => null,
                'finished_at' => null,
            ]);

            $job->update([
                'status' => 'running',
                'failed_sequences' => 0,
                'finished_at' => null,
                'error_message' => null,
            ]);
        });

        foreach ($failedItemIds as $itemId) {
            AnalyzeSequenceJob::dispatch($itemId);
        }

        return $job->refresh();
    }
}
