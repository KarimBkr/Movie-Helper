<?php

namespace App\Jobs;

use App\Actions\PersistBreakdownAction;
use App\Models\AnalysisJob;
use App\Models\AnalysisJobItem;
use App\Models\Sequence;
use App\Services\Claude\ClaudeAnalysisService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Analyse IA d'UNE séquence (L-05).
 *
 * Une unité de travail = un AnalysisJobItem. Le job appelle Claude
 * (ClaudeAnalysisService), persiste le dépouillement (PersistBreakdownAction),
 * met à jour l'item puis recalcule l'avancement du AnalysisJob parent.
 *
 * CLAUDE.md règle 5 : file 'ai', tries=2, backoff=30, max 2 workers (appliqué
 * au lancement de queue:work, pas dans le code).
 */
class AnalyzeSequenceJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 2;

    public int $backoff = 30;

    public function __construct(
        public readonly string $itemId,
    ) {
        $this->onQueue(config('queue.ai.queue', 'ai'));
        $this->tries = (int) config('queue.ai.tries', 2);
        $this->backoff = (int) config('queue.ai.backoff', 30);
    }

    public function handle(ClaudeAnalysisService $claude, PersistBreakdownAction $persist): void
    {
        $item = AnalysisJobItem::find($this->itemId);

        // Item disparu (job annulé / supprimé) → rien à faire.
        if ($item === null) {
            return;
        }

        $sequence = Sequence::find($item->sequence_id);

        if ($sequence === null) {
            $this->markItemFailed($item, 'Séquence introuvable.');

            return;
        }

        $item->update([
            'status' => 'running',
            'attempts' => $item->attempts + 1,
            'started_at' => $item->started_at ?? now(),
            'error_message' => null,
        ]);

        // Une exception ici fait échouer le job → retry automatique (tries=2),
        // puis failed() après épuisement des tentatives.
        $breakdown = $claude->analyzeSequence($sequence->raw_text);

        $persist->execute($sequence, $breakdown);

        $item->update([
            'status' => 'completed',
            'claude_tool_use_id' => $breakdown->toolUseId,
            'raw_tool_input' => $breakdown->rawInput,
            'finished_at' => now(),
            'error_message' => null,
        ]);

        $this->refreshJobProgress($item->analysis_job_id);
    }

    /**
     * Appelé par Laravel quand toutes les tentatives ont échoué.
     */
    public function failed(?Throwable $e): void
    {
        $item = AnalysisJobItem::find($this->itemId);

        if ($item === null) {
            return;
        }

        $this->markItemFailed($item, $e?->getMessage() ?? 'Échec inconnu.');
        $this->refreshJobProgress($item->analysis_job_id);
    }

    private function markItemFailed(AnalysisJobItem $item, string $message): void
    {
        $item->update([
            'status' => 'failed',
            'error_message' => $message,
            'finished_at' => now(),
        ]);
    }

    /**
     * Recalcule les compteurs et le statut du AnalysisJob à partir de ses items.
     * Transaction + verrou pour éviter les courses entre workers parallèles.
     */
    private function refreshJobProgress(string $jobId): void
    {
        DB::transaction(function () use ($jobId): void {
            $job = AnalysisJob::lockForUpdate()->find($jobId);

            if ($job === null) {
                return;
            }

            $counts = AnalysisJobItem::where('analysis_job_id', $jobId)
                ->selectRaw('count(*) filter (where status = \'completed\') as done')
                ->selectRaw('count(*) filter (where status = \'failed\') as failed')
                ->first();

            $done = (int) $counts->done;
            $failed = (int) $counts->failed;
            $finished = $done + $failed;

            $update = [
                'processed_sequences' => $done,
                'failed_sequences' => $failed,
            ];

            // Tous les items terminés → statut final du job.
            if ($finished >= $job->total_sequences && $job->total_sequences > 0) {
                $update['status'] = match (true) {
                    $failed === 0 => 'completed',
                    $done === 0 => 'failed',
                    default => 'partial',
                };
                $update['finished_at'] = now();
            } elseif ($job->status === 'pending') {
                $update['status'] = 'running';
                $update['started_at'] = $job->started_at ?? now();
            }

            $job->update($update);
        });
    }
}
